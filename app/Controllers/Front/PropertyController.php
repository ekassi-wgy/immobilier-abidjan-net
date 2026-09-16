<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Site;
use App\Services\IpAddress;
use App\Support\Validator;

/**
 * Fiche annonce publique (lot 1.10) : /annonces/{slug}-ref{id}
 *
 * Galerie, description, critères structurés, situation juridique, carte, bloc agence,
 * demande de contact, biens similaires et partage.
 *
 * Aucune lecture ne touche `property_private_details` : les informations de notaire et de
 * dossier restent internes, comme l'adresse exacte d'une annonce qui ne l'a pas rendue publique.
 */
final class PropertyController extends Controller
{
    private const SIMILAR = 3;

    /** Anti-spam : demandes de contact acceptées par adresse IP sur une heure glissante. */
    private const CONTACT_MAX = 5;
    private const CONTACT_WINDOW = 3600;

    /** Cookie de dédoublonnage des consultations (une vue par annonce et par visiteur, 12 h). */
    private const SEEN_COOKIE = 'ian_seen';
    private const SEEN_MAX = 40;

    public function show(Request $request, string $slug, string $id): Response
    {
        [$site, $property] = $this->findOrFail((int) $id);

        // URL canonique : un slug différent (titre modifié, lien ancien) redirige en 301.
        if ($slug !== (string) $property['slug']) {
            return $this->redirect($this->app->listingPresenter()->url($property), 301);
        }

        $this->countView($request, (int) $property['id']);

        return $this->render($request, $site, $property);
    }

    /** Demande de contact du visiteur : crée un lead, prévient l'agence et l'équipe du pays. */
    public function contact(Request $request, string $slug, string $id): Response
    {
        [$site, $property] = $this->findOrFail((int) $id);
        $presenter = $this->app->listingPresenter();
        $url = $presenter->url($property);

        $input = $request->all();
        $validator = $this->validate($input);

        // Pot de miel : un robot remplit tous les champs, un visiteur ne voit jamais celui-ci.
        $trapped = trim((string) ($input['site_web'] ?? '')) !== '';
        $allowed = $this->app->rateLimiter()->attempt('lead:' . $request->ip(), self::CONTACT_MAX, self::CONTACT_WINDOW);

        if ($validator->fails()) {
            return $this->render($request, $site, $property, $validator->errors(), $input, 422);
        }
        if (!$allowed) {
            return $this->render($request, $site, $property, ['message' => __('front.contact.too_many')], $input, 429);
        }

        // Un envoi piégé reçoit la même confirmation qu'un envoi valide, sans rien enregistrer.
        if (!$trapped) {
            $this->createLead($request, $site->id, $property, $validator);
        }

        $this->flash('success', __('front.contact.success'));

        return $this->redirect($url . '#contact', 303);
    }

    /**
     * Annonce en ligne du pays courant, ou 404. Une annonce en attente, rejetée, dépubliée,
     * archivée, expirée ou supprimée n'existe pas pour le site public.
     *
     * @return array{0: Site, 1: array<string, mixed>}
     */
    private function findOrFail(int $id): array
    {
        $site = site() ?? throw new HttpException(404);
        $property = $this->app->listings()->findPublished($id, $site->country->id) ?? throw new HttpException(404);

        return [$site, $property];
    }

    /**
     * @param array<string, mixed>  $property
     * @param array<string, string> $errors
     * @param array<string, mixed>  $old
     */
    private function render(Request $request, Site $site, array $property, array $errors = [], array $old = [], int $status = 200): Response
    {
        $listings = $this->app->listings();
        $presenter = $this->app->listingPresenter();
        $id = (int) $property['id'];

        $detail = $presenter->detail(
            $property,
            $listings->publicImages($id),
            $listings->criteriaRows($id, (int) $property['category_id'], $property['category_parent_id'] !== null ? (int) $property['category_parent_id'] : null, $property),
            $listings->publicFeatures($id)
        );

        $similar = $presenter->cards($listings->similar($property, $site->country->id, self::SIMILAR));
        $cover = $detail['gallery'][0] ?? null;

        return $this->page('front/layouts/app', 'front/pages/property', [
            'property' => $detail,
            'similar' => $similar,
            'breadcrumb' => $this->breadcrumb($property, $detail),
            'errors' => $errors,
            'old' => $old,
            'flash' => $this->app->session()->flashes(),
            'csrfToken' => $this->app->csrf()->token(),
            'shareUrl' => absolute_url($detail['url']),
        ], [
            'title' => !empty($property['meta_title']) ? (string) $property['meta_title'] : $this->metaTitle($detail),
            'description' => !empty($property['meta_description'])
                ? (string) $property['meta_description']
                : mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($detail['description'])) ?? ''), 0, 300),
            'canonical' => absolute_url($detail['url']),
            'ogImage' => $cover !== null ? absolute_url(ltrim($cover['src'], '/')) : null,
            'preloadImage' => $cover !== null ? $cover['src'] : null,
            'pageScripts' => $detail['map'] !== null
                ? ['vendors/leaflet/leaflet.js', 'js/property.js']
                : ['js/property.js'],
            'pageStyles' => $detail['map'] !== null ? ['vendors/leaflet/leaflet.css'] : [],
            'schema' => $this->schema($detail, $site),
        ], $status);
    }

    /** « Villa 5 pièces à vendre à Cocody · 185 000 000 FCFA » */
    private function metaTitle(array $detail): string
    {
        return __('front.property.meta_title', [
            'title' => $detail['title'],
            'location' => $detail['location'],
            'price' => $detail['price_label'],
        ]);
    }

    /**
     * Fil d'Ariane : chaque étape est une page de résultats valide, la dernière est l'annonce.
     *
     * @return list<array{label: string, path: string, current: bool}>
     */
    private function breadcrumb(array $property, array $detail): array
    {
        $items = [['label' => __('front.nav.home'), 'path' => '', 'current' => false]];
        $path = $detail['transaction_slug'];
        $items[] = ['label' => $detail['transaction'], 'path' => $path, 'current' => false];

        $path .= '/' . $detail['category_slug'];
        $items[] = ['label' => $detail['category'], 'path' => $path, 'current' => false];

        foreach ([['city_slug', 'city_name'], ['commune_slug', 'commune_name']] as [$slugKey, $nameKey]) {
            if ($property[$slugKey] === null) {
                continue;
            }
            $path .= '/' . $property[$slugKey];
            $items[] = ['label' => (string) $property[$nameKey], 'path' => $path, 'current' => false];
        }

        $items[] = ['label' => $detail['title'], 'path' => $detail['url'], 'current' => true];

        return $items;
    }

    /** @param array<string, mixed> $input */
    private function validate(array $input): Validator
    {
        $validator = new Validator($input);
        $validator->required('name', 'message')
            ->maxLength('name', 150)
            ->maxLength('message', 2000)
            ->maxLength('email', 190)
            ->maxLength('phone', 30);

        if (trim((string) ($input['email'] ?? '')) !== '') {
            $validator->email('email');
        }
        if (trim((string) ($input['phone'] ?? '')) !== '') {
            $validator->phone('phone');
        }

        // La table `leads` impose au moins un moyen de recontact.
        $validator->rule(
            'email',
            trim((string) ($input['email'] ?? '')) !== '' || trim((string) ($input['phone'] ?? '')) !== '',
            __('front.contact.contact_required')
        );
        $validator->rule('consent', !empty($input['consent']), __('front.contact.consent_required'));

        return $validator;
    }

    /** @param array<string, mixed> $property */
    private function createLead(Request $request, int $siteId, array $property, Validator $validator): void
    {
        $leadId = $this->app->db()->insert('leads', [
            'site_id' => $siteId,
            'country_id' => (int) site()->country->id,
            'type' => 'property_contact',
            'property_id' => (int) $property['id'],
            'agency_id' => $property['agency_id'] !== null ? (int) $property['agency_id'] : null,
            'name' => $validator->string('name'),
            'email' => $validator->nullableString('email'),
            'phone' => $validator->nullableString('phone'),
            'message' => $validator->nullableString('message'),
            'consent_at' => gmdate('Y-m-d H:i:s'),
            'source_url' => mb_substr(absolute_url($this->app->listingPresenter()->url($property)), 0, 255),
            'ip' => IpAddress::toBinary($request->ip()),
            'user_agent' => mb_substr($request->userAgent(), 0, 255),
        ]);

        $this->app->listings()->recordLead((int) $property['id']);

        $recipients = $this->app->notifier()->propertyRecipients($property);
        if ($recipients === []) {
            $recipients = $this->app->notifier()->staffRecipients((int) site()->country->id)['all'];
        }

        $this->app->notifier()->notify(
            $recipients,
            'lead.new',
            __('front.contact.notification_title', ['reference' => (string) $property['reference']]),
            __('front.contact.notification_body', ['name' => $validator->string('name'), 'title' => (string) $property['title']]),
            cmsadmin_url('contacts/' . $leadId),
            site(),
            $recipients
        );
    }

    /**
     * Une consultation par annonce et par visiteur sur 12 heures. Le cookie ne contient que des
     * identifiants d'annonces publiques : ni session, ni donnée personnelle.
     */
    private function countView(Request $request, int $propertyId): void
    {
        $seen = array_filter(array_map('intval', explode(',', (string) $request->cookie(self::SEEN_COOKIE, ''))));
        if (in_array($propertyId, $seen, true)) {
            return;
        }

        $this->app->listings()->recordView($propertyId);

        $seen = array_slice([$propertyId, ...$seen], 0, self::SEEN_MAX);
        setcookie(self::SEEN_COOKIE, implode(',', $seen), [
            'expires' => time() + 43200,
            'path' => '/',
            'secure' => $request->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Données structurées : l'annonce (Schema.org RealEstateListing) et le fil d'Ariane.
     *
     * @return array<string, mixed>
     */
    private function schema(array $detail, Site $site): array
    {
        $listing = [
            '@type' => 'RealEstateListing',
            'name' => $detail['title'],
            'url' => absolute_url($detail['url']),
            'description' => mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($detail['description'])) ?? ''), 0, 500),
            'datePosted' => $detail['published_at'],
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $detail['address'],
                'addressLocality' => $detail['commune'] ?? $detail['city'],
                'addressRegion' => $detail['city'],
                'addressCountry' => $site->country->iso2,
            ]),
        ];

        if ($detail['gallery'] !== []) {
            $listing['image'] = array_map(
                static fn (array $image): string => absolute_url(ltrim($image['full'], '/')),
                array_slice($detail['gallery'], 0, 6)
            );
        }
        if ($detail['price'] !== null) {
            $listing['offers'] = [
                '@type' => 'Offer',
                'price' => $detail['price'],
                'priceCurrency' => $site->country->currencyCode,
                'availability' => $detail['availability'] === 'available'
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
            ];
        }
        if ($detail['map'] !== null) {
            $listing['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $detail['map']['lat'], 'longitude' => $detail['map']['lng']];
        }
        if ($detail['agency'] !== null) {
            $listing['provider'] = ['@type' => 'RealEstateAgent', 'name' => $detail['agency']['name'], 'url' => absolute_url($detail['agency']['url'])];
        }

        return ['@context' => 'https://schema.org', '@graph' => [$listing]];
    }
}
