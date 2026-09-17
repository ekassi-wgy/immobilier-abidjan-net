<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Models\Site;
use App\Models\User;
use App\Support\Str;
use App\Support\Validator;

/**
 * Validation du formulaire d'annonce et construction de la « charge utile » enregistrée
 * (directement, ou sous forme de révision pour une annonce publiée modifiée par une agence).
 *
 * Charge utile :
 *   fields     colonnes de properties (dont les critères stockés en colonne)
 *   attributes [attribute_id => valeur | list<option_id>] (critères EAV)
 *   features   list<feature_id>
 *   images     list<['id' => ?int, 'token' => ?string, 'alt' => ?string]> dans l'ordre (le premier = couverture)
 *   private    notaire, référence de dossier, notes (+ propriétaire pour un bien de particulier)
 *   document   ['file' => array|null, 'remove' => bool]
 *   featured   ['is_featured' => int, 'featured_until' => ?string] (équipe uniquement)
 */
final class PropertyForm
{
    private const TITLE_MIN = 10;
    private const DESCRIPTION_MIN = 40;

    /**
     * Brouillon en cours de validation : critères obligatoires, photo et prix peuvent manquer.
     * Titre, description, catégorie, transaction et ville restent exigés (colonnes NOT NULL), et
     * tout est de nouveau contrôlé quand le brouillon est envoyé.
     */
    private bool $draft = false;

    public function __construct(
        private readonly CatalogRepository $catalog,
        private readonly GeoRepository $geo,
        private readonly AgencyRepository $agencies,
        private readonly UserRepository $users,
        private readonly PendingUploads $uploads,
        private readonly ImageUploader $images,
        private readonly int $maxPhotos,
        private readonly int $maxDocumentBytes = 10 * 1024 * 1024,
    ) {
    }

    /**
     * @param array<string, mixed>|null      $property Annonce existante (modification)
     * @param list<array<string, mixed>>     $currentImages Photos actuelles (en ligne + révision en cours)
     * @return array{0: array<string, mixed>, 1: array<string, string>, 2: array<string, mixed>|null} payload, erreurs, schéma
     */
    public function validate(Request $request, User $user, Site $site, ?array $property, array $currentImages, bool $draft = false): array
    {
        $this->draft = $draft;
        $input = $request->all();
        // Montants saisis avec séparateurs de milliers : « 185 000 000 »
        foreach (['price', 'charges', 'agency_fee_percent', 'latitude', 'longitude'] as $numeric) {
            if (isset($input[$numeric]) && is_string($input[$numeric])) {
                $input[$numeric] = str_replace(["\u{00A0}", "\u{202F}", ' '], '', $input[$numeric]);
            }
        }
        $v = new Validator($input);
        $countryId = $site->country->id;

        // Catégorie et schéma des critères
        $categoryId = $v->nullableInt('category_id');
        $schema = $categoryId !== null ? $this->catalog->formSchema($categoryId, $countryId) : null;
        $v->required('category_id')->rule('category_id', $categoryId === null || $schema !== null, __('validation.in'));

        $transactionId = $v->nullableInt('transaction_type_id');
        $v->required('transaction_type_id')->rule('transaction_type_id', $transactionId === null || $schema === null || isset($schema['transactions'][$transactionId]), __('properties.errors.transaction_not_allowed'));

        // Origine : l'agence pour un compte agence ; au choix pour l'équipe
        [$source, $agencyId] = $this->source($v, $user, $countryId, $property);
        $agentId = $v->nullableInt('agent_user_id');
        if ($agentId !== null) {
            $agent = $this->users->row($agentId);
            $v->rule('agent_user_id', $agent !== null && $agencyId !== null && (int) $agent['agency_id'] === $agencyId && (int) $agent['is_active'] === 1, __('validation.in'));
        }

        // Contenu
        $v->required('title')->maxLength('title', 160)
            ->rule('title', $v->string('title') === '' || mb_strlen($v->string('title')) >= self::TITLE_MIN, __('properties.errors.title_short', ['min' => self::TITLE_MIN]))
            ->required('description')->maxLength('description', 20000)
            ->rule('description', $v->string('description') === '' || mb_strlen($v->string('description')) >= self::DESCRIPTION_MIN, __('properties.errors.description_short', ['min' => self::DESCRIPTION_MIN]))
            ->maxLength('internal_reference', 50);

        // Prix
        $onRequest = $v->bool('price_on_request');
        if (!$onRequest && !$this->draft) {
            $v->required('price');
        }
        $v->decimal('price', 0, 9999999999999)->decimal('charges', 0, 9999999999)->decimal('agency_fee_percent', 0, 100)
            ->in('price_period', PropertyRepository::PRICE_PERIODS);

        // Localisation
        $cityId = $v->nullableInt('city_id');
        $v->required('city_id')->rule('city_id', $cityId === null || $this->geo->city($cityId, $countryId) !== null, __('validation.in'));
        $communeId = $v->nullableInt('commune_id');
        $commune = $communeId !== null ? $this->geo->commune($communeId, $countryId) : null;
        $v->rule('commune_id', $communeId === null || ($commune !== null && (int) $commune['city_id'] === $cityId), __('agencies.commune_not_in_city'));
        if ($cityId !== null && $communeId === null && $this->geo->communeOptions($countryId, $cityId) !== []) {
            $v->add('commune_id', __('validation.required'));
        }
        $districtId = $v->nullableInt('district_id');
        $district = $districtId !== null ? $this->geo->district($districtId, $countryId) : null;
        $v->rule('district_id', $districtId === null || ($district !== null && (int) $district['commune_id'] === $communeId), __('properties.errors.district_not_in_commune'));
        $v->maxLength('address', 255)->decimal('latitude', -90, 90)->decimal('longitude', -180, 180)
            ->rule('longitude', ($v->nullableDecimal('latitude') === null) === ($v->nullableDecimal('longitude') === null), __('properties.errors.coordinates_pair'));

        // Disponibilité, médias externes, contact
        $v->required('availability')->in('availability', PropertyRepository::AVAILABILITIES)
            ->rule('available_from', $v->string('available_from') === '' || $this->isDate($v->string('available_from')), __('properties.errors.date'))
            ->maxLength('video_url', 255)->rule('video_url', $v->string('video_url') === '' || $this->isVideoUrl($v->string('video_url')), __('properties.errors.video_url'))
            ->maxLength('virtual_tour_url', 255)->rule('virtual_tour_url', $v->string('virtual_tour_url') === '' || $this->isHttpsUrl($v->string('virtual_tour_url')), __('properties.errors.tour_url'))
            ->maxLength('contact_name', 120)->maxLength('contact_phone', 30)->phone('contact_phone')
            ->maxLength('contact_whatsapp', 30)->phone('contact_whatsapp')->maxLength('contact_email', 190)->email('contact_email');

        // Critères dynamiques
        $attributes = [];
        $columns = array_fill_keys(PropertyRepository::COLUMN_ATTRIBUTES, null);
        foreach ($schema['attributes'] ?? [] as $id => $attribute) {
            $value = $this->attributeValue($v, $attribute, $input['attributes'][$id] ?? null, $id);
            if ($attribute['storage'] === 'column') {
                $columns[$attribute['column_name']] = $value;
            } else {
                $attributes[$id] = $value;
            }
        }

        // Équipements
        $allowedFeatures = [];
        foreach ($this->catalog->featureChoices() as $group) {
            $allowedFeatures += $group;
        }
        $features = array_values(array_filter(array_map('intval', $v->list('features')), static fn (int $id): bool => isset($allowedFeatures[$id])));

        // Photos (ordre du formulaire)
        $images = $this->images($input['photos'] ?? [], $currentImages, $v);

        // Document PDF
        $documentFile = $request->file('document');
        $documentError = $this->images->checkPdf(is_array($documentFile) ? $documentFile : null, $this->maxDocumentBytes);
        if ($documentError !== null && $documentError !== 'none') {
            $v->add('document', __($documentError, ['max' => '10 Mo']));
        }

        // Informations internes
        $v->maxLength('notary_name', 150)->maxLength('notary_reference', 100)->maxLength('internal_notes', 5000);
        $private = [
            'notary_name' => $v->nullableString('notary_name'),
            'notary_reference' => $v->nullableString('notary_reference'),
            'internal_notes' => $v->nullableString('internal_notes'),
        ];
        if ($user->isStaff()) {
            $v->maxLength('owner_name', 150)->maxLength('owner_phone', 30)->phone('owner_phone')->maxLength('owner_email', 190)->email('owner_email');
            $private += [
                'owner_name' => $source === 'private_owner' ? $v->nullableString('owner_name') : null,
                'owner_phone' => $source === 'private_owner' ? $v->nullableString('owner_phone') : null,
                'owner_email' => $source === 'private_owner' ? $v->nullableString('owner_email') : null,
            ];
        }

        // Mise en avant : équipe uniquement
        $featured = null;
        if ($user->isStaff()) {
            $v->rule('featured_until', $v->string('featured_until') === '' || $this->isDate($v->string('featured_until')), __('properties.errors.date'));
            $featured = [
                'is_featured' => $v->bool('is_featured') ? 1 : 0,
                'featured_until' => $v->bool('is_featured') && $this->isDate($v->string('featured_until')) ? $v->string('featured_until') . ' 23:59:59' : null,
            ];
        }

        $title = $v->string('title');
        $fields = [
            'source' => $source,
            'agency_id' => $agencyId,
            'agent_user_id' => $agentId,
            'transaction_type_id' => $transactionId,
            'category_id' => $categoryId,
            'title' => $title,
            'slug' => Str::slug(implode(' ', array_filter([$title, $district['name'] ?? null, $commune['name'] ?? null])), 190),
            'description' => $v->string('description'),
            'internal_reference' => $v->nullableString('internal_reference'),
            'price' => $onRequest ? null : $v->nullableDecimal('price'),
            'price_period' => $v->string('price_period') !== '' ? $v->string('price_period') : ($schema['transactions'][$transactionId]['default_price_period'] ?? 'total'),
            'is_negotiable' => $v->bool('is_negotiable') ? 1 : 0,
            'charges' => $v->nullableDecimal('charges'),
            'agency_fee_percent' => $v->nullableDecimal('agency_fee_percent'),
            'city_id' => $cityId,
            'commune_id' => $communeId,
            'district_id' => $districtId,
            'address' => $v->nullableString('address'),
            'latitude' => $v->nullableDecimal('latitude'),
            'longitude' => $v->nullableDecimal('longitude'),
            'show_exact_location' => $v->bool('show_exact_location') ? 1 : 0,
            'availability' => $v->string('availability') !== '' ? $v->string('availability') : 'available',
            'available_from' => $this->isDate($v->string('available_from')) ? $v->string('available_from') : null,
            'video_url' => $v->nullableString('video_url'),
            'virtual_tour_url' => $v->nullableString('virtual_tour_url'),
            'contact_name' => $v->nullableString('contact_name'),
            'contact_phone' => $v->nullableString('contact_phone'),
            'contact_whatsapp' => $v->nullableString('contact_whatsapp'),
            'contact_email' => $v->nullableString('contact_email') !== null ? mb_strtolower($v->string('contact_email')) : null,
        ] + $columns;

        return [[
            'fields' => $fields,
            'attributes' => $attributes,
            'features' => $features,
            'images' => $images,
            'private' => $private,
            'document' => ['file' => $documentError === null ? $documentFile : null, 'remove' => $v->bool('remove_document')],
            'featured' => $featured,
        ], $v->errors(), $schema];
    }

    /** @return array{0: string, 1: ?int} */
    private function source(Validator $v, User $user, int $countryId, ?array $property): array
    {
        if ($user->isAgency()) {
            return ['agency', $user->agencyId];
        }

        $source = $v->string('source') !== '' ? $v->string('source') : 'agency';
        $v->in('source', PropertyRepository::SOURCES);
        if ($source !== 'agency') {
            return [in_array($source, PropertyRepository::SOURCES, true) ? $source : 'platform', null];
        }

        $agencyId = $v->nullableInt('agency_id');
        $agency = $agencyId !== null ? $this->agencies->find($agencyId, $countryId) : null;
        $v->required('agency_id')->rule('agency_id', $agencyId === null || ($agency !== null && ($agency['status'] === 'active' || (int) ($property['agency_id'] ?? 0) === $agencyId)), __('properties.errors.agency_inactive'));

        return ['agency', $agencyId];
    }

    /**
     * Valeur d'un critère selon son type (null si vide). Erreurs rattachées au champ « attributes.{id} ».
     *
     * @param array<string, mixed> $attribute
     */
    private function attributeValue(Validator $v, array $attribute, mixed $raw, int $id): mixed
    {
        $field = 'attributes.' . $id;
        $required = (int) $attribute['is_required'] === 1 && !$this->draft;
        $type = (string) $attribute['input_type'];

        if ($type === 'multiselect') {
            $values = array_values(array_unique(array_map('intval', is_array($raw) ? $raw : [])));
            $values = array_values(array_filter($values, static fn (int $option): bool => isset($attribute['options'][$option])));
            if ($required && $values === []) {
                $v->add($field, __('validation.required'));
            }

            return $values;
        }

        if ($type === 'boolean') {
            return in_array((string) $raw, ['0', '1'], true) ? (int) $raw : null;
        }

        $value = is_scalar($raw) ? trim(str_replace(["\u{00A0}", ' '], '', (string) $raw)) : '';
        if ($type === 'text') {
            $value = is_scalar($raw) ? trim((string) $raw) : '';
        }
        if ($value === '') {
            if ($required) {
                $v->add($field, __('validation.required'));
            }

            return null;
        }

        $min = $attribute['min_value'] !== null ? (float) $attribute['min_value'] : null;
        $max = $attribute['max_value'] !== null ? (float) $attribute['max_value'] : null;

        switch ($type) {
            case 'integer':
            case 'year':
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $v->add($field, __('validation.integer'));

                    return null;
                }
                $number = (int) $value;
                if ($type === 'year' && ($number < 1800 || $number > (int) gmdate('Y') + 10)) {
                    $v->add($field, __('validation.between', ['min' => 1800, 'max' => (int) gmdate('Y') + 10]));
                }
                break;
            case 'decimal':
                $value = str_replace(',', '.', $value);
                if (!is_numeric($value)) {
                    $v->add($field, __('validation.decimal'));

                    return null;
                }
                $number = (float) $value;
                break;
            case 'date':
                if (!$this->isDate($value)) {
                    $v->add($field, __('properties.errors.date'));
                }

                return $value;
            case 'select':
                if (!isset($attribute['options'][(int) $value])) {
                    $v->add($field, __('validation.in'));

                    return null;
                }

                return (int) $value;
            default:
                if (mb_strlen($value) > 500) {
                    $v->add($field, __('validation.max_length', ['max' => 500]));
                }

                return $value;
        }

        if (($min !== null && $number < $min) || ($max !== null && $number > $max) || $number < 0) {
            $v->add($field, __('validation.between', ['min' => format_number($min ?? 0), 'max' => $max !== null ? format_number($max) : '∞']));
        }

        return $type === 'decimal' ? $value : (int) $value;
    }

    /**
     * @param list<array<string, mixed>> $current
     * @return list<array{id: ?int, token: ?string, alt: ?string}>
     */
    private function images(mixed $raw, array $current, Validator $v): array
    {
        $known = array_column($current, null, 'id');
        $images = [];
        foreach (is_array($raw) ? $raw : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $alt = isset($row['alt']) && is_scalar($row['alt']) ? mb_substr(trim((string) $row['alt']), 0, 190) : '';
            if (!empty($row['id']) && isset($known[(int) $row['id']])) {
                $images[] = ['id' => (int) $row['id'], 'token' => null, 'alt' => $alt !== '' ? $alt : null];
            } elseif (!empty($row['token']) && is_string($row['token']) && $this->uploads->get($row['token']) !== null) {
                $images[] = ['id' => null, 'token' => $row['token'], 'alt' => $alt !== '' ? $alt : null];
            }
        }

        if ($images === [] && !$this->draft) {
            $v->add('photos', __('properties.errors.photos_required'));
        } elseif (count($images) > $this->maxPhotos) {
            $v->add('photos', __('properties.errors.photos_max', ['max' => $this->maxPhotos]));
        }

        return $images;
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function isHttpsUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false && str_starts_with(strtolower($url), 'https://');
    }

    private function isVideoUrl(string $url): bool
    {
        if (!$this->isHttpsUrl($url)) {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true);
    }
}
