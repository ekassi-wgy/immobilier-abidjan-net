<?php

declare(strict_types=1);

namespace App\Controllers\Front\Owner;

use App\Controllers\Front\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Services\IpAddress;
use App\Services\PrivateFiles;
use App\Support\Validator;
use Throwable;

/**
 * « Confiez-nous votre bien » : un particulier transmet son bien à Weblogy, qui l'étudie puis le
 * publie et le commercialise. Le particulier ne publie jamais lui-même.
 *
 * - /confiez-nous-votre-bien : présentation du service, publique ;
 * - /mon-espace/biens/…     : formulaire et suivi, compte obligatoire et adresse email confirmée.
 *
 * Photos et documents sont stockés hors du dossier public (PrivateFiles) : ils ne deviennent
 * visibles qu'à travers l'annonce que l'équipe crée à partir du dossier.
 */
final class SubmissionController extends Controller
{
    private const MAX_PHOTOS = 15;
    private const MAX_DOCUMENTS = 5;
    private const MAX_PHOTO_MB = 8;
    private const MAX_DOCUMENT_MB = 5;
    private const DESCRIPTION_MIN = 30;

    /** Page publique : explique le mandat confié à Weblogy et oriente vers l'inscription ou le formulaire. */
    public function landing(Request $request): Response
    {
        $site = $this->site();

        return $this->page('front/layouts/app', 'front/pages/owner/landing', [
            'user' => $this->app->ownerAuth()->user($request),
        ], [
            'title' => __('owner.landing.meta_title'),
            'description' => __('owner.landing.meta_description', ['site' => $site->name]),
            'canonical' => absolute_url('confiez-nous-votre-bien'),
        ]);
    }

    /** Ancienne adresse « Déposer un bien » : redirection permanente. */
    public function legacy(Request $request): Response
    {
        return $this->redirect('confiez-nous-votre-bien', 301);
    }

    public function create(Request $request): Response
    {
        $user = $this->owner($request);
        if (!$user->hasVerifiedEmail()) {
            $this->flash('info', __('owner.verify.required'));

            return $this->redirect('mon-espace', 303);
        }

        return $this->form($request, $user);
    }

    public function store(Request $request): Response
    {
        $site = $this->site();
        $user = $this->owner($request);
        if (!$user->hasVerifiedEmail()) {
            throw new HttpException(403);
        }

        [$v, $data] = $this->validate($request, $site->country->id);
        $files = $this->app->privateFiles();

        $photos = array_slice(PrivateFiles::normalize($request->file('photos')), 0, self::MAX_PHOTOS + 1);
        if ($photos === []) {
            $v->add('photos', __('owner.submission.photos_required'));
        } elseif (count($photos) > self::MAX_PHOTOS) {
            $v->add('photos', __('owner.submission.photos_max', ['max' => self::MAX_PHOTOS]));
        }
        foreach ($photos as $photo) {
            $error = $files->checkPhoto($photo, self::MAX_PHOTO_MB * 1048576);
            if ($error !== null) {
                $v->add('photos', __($error, ['max' => self::MAX_PHOTO_MB . ' Mo']) . ' (' . mb_substr((string) $photo['name'], 0, 60) . ')');
                break;
            }
        }
        $documents = PrivateFiles::normalize($request->file('documents'));
        if (count($documents) > self::MAX_DOCUMENTS) {
            $v->add('documents', __('owner.submission.documents_max', ['max' => self::MAX_DOCUMENTS]));
        }
        foreach ($documents as $document) {
            $error = $files->checkDocument($document, self::MAX_DOCUMENT_MB * 1048576);
            if ($error !== null) {
                $v->add('documents', __($error, ['max' => self::MAX_DOCUMENT_MB . ' Mo']) . ' (' . mb_substr((string) $document['name'], 0, 60) . ')');
                break;
            }
        }

        if ($v->fails()) {
            return $this->form($request, $user, $v->errors(), $request->all(), 422);
        }
        if (!$this->withinQuota($request, 'owner-submission')) {
            return $this->form($request, $user, ['message' => __('front.contact.too_many')], $request->all(), 429);
        }

        // Fichiers écrits d'abord dans un dossier propre à l'envoi, puis rattachés au dossier en base.
        $directory = strtolower($site->country->iso2) . '/biens-confies/' . $user->id . '/' . gmdate('Ymd') . '-' . bin2hex(random_bytes(4));
        $stored = [];
        try {
            foreach ($photos as $photo) {
                $stored[] = ['kind' => 'photo'] + $files->storePhoto($photo, $directory);
            }
            foreach ($documents as $document) {
                $stored[] = ['kind' => 'document'] + $files->storeDocument($document, $directory);
            }

            $id = $this->app->submissions()->create($data + [
                'site_id' => $site->id,
                'country_id' => $site->country->id,
                'user_id' => $user->id,
                'status' => 'submitted',
                'consent_at' => gmdate('Y-m-d H:i:s'),
                'ip' => IpAddress::toBinary($request->ip()),
            ], $stored);
        } catch (Throwable $exception) {
            foreach ($stored as $file) {
                $files->delete((string) $file['path']);
            }
            $this->app->logger()->exception($exception, ['form' => 'owner-submission', 'user' => $user->id]);

            return $this->form($request, $user, ['message' => __('owner.submission.save_failed')], $request->all(), 500);
        }

        $this->app->activity()->log('submission.created', $user->id, $site->country->id, 'submission', $id, $user->fullName(), request: $request);
        $recipients = $this->app->notifier()->staffRecipients($site->country->id);
        $this->app->notifier()->notify(
            $recipients['all'],
            'submission.new',
            __('submissions.notify.new_title', ['name' => $user->fullName()]),
            __('submissions.notify.new_body', ['type' => (string) $this->app->db()->scalar('SELECT name FROM property_categories WHERE id = :id', ['id' => $data['category_id']])]),
            cmsadmin_url('biens-confies/' . $id),
            $site,
            $recipients['email']
        );
        $this->app->ownerMessages()->send(
            $user,
            $site,
            __('owner.submission.received_subject', ['site' => $site->name]),
            __('owner.submission.received_title'),
            __('owner.submission.received_body'),
            absolute_url('mon-espace/biens/' . $id)
        );

        $this->flash('success', __('owner.submission.sent'));

        return $this->redirect('mon-espace/biens/' . $id, 303);
    }

    public function show(Request $request, string $id): Response
    {
        $user = $this->owner($request);
        $site = $this->site();
        $submission = $this->app->submissions()->findForOwner((int) $id, $user->id, $site->country->id) ?? throw new HttpException(404);

        return $this->page('front/layouts/app', 'front/pages/owner/submission', [
            'user' => $user,
            'submission' => $submission,
            'photos' => $this->app->submissions()->files((int) $submission['id'], 'photo'),
            'documents' => $this->app->submissions()->files((int) $submission['id'], 'document'),
            'flash' => $this->app->session()->flashes(),
            'csrfToken' => $this->app->csrf()->token(),
        ], ['title' => __('owner.submission.show_title', ['id' => $submission['id']]), 'description' => __('owner.meta_description', ['site' => $site->name]), 'noindex' => true]);
    }

    public function withdraw(Request $request, string $id): Response
    {
        $user = $this->owner($request);
        $submission = $this->app->submissions()->findForOwner((int) $id, $user->id, $this->site()->country->id) ?? throw new HttpException(404);

        if ($this->app->submissions()->withdraw((int) $submission['id'], $user->id)) {
            $this->app->activity()->log('submission.withdrawn', $user->id, $user->countryId, 'submission', (int) $submission['id'], request: $request);
            $this->flash('success', __('owner.submission.withdrawn'));
        } else {
            $this->flash('info', __('owner.submission.withdraw_refused'));
        }

        return $this->redirect('mon-espace/biens/' . $submission['id'], 303);
    }

    /** Photo d'un dossier, pour son propriétaire uniquement (aperçu dans l'espace). */
    public function photo(Request $request, string $id, string $file): Response
    {
        $user = $this->owner($request);
        $submission = $this->app->submissions()->findForOwner((int) $id, $user->id, $this->site()->country->id) ?? throw new HttpException(404);
        $photo = $this->app->submissions()->file((int) $submission['id'], (int) $file);
        if ($photo === null || $photo['kind'] !== 'photo') {
            throw new HttpException(404);
        }

        return $this->app->privateFiles()->response((string) $photo['path'], (string) $photo['mime'], (string) $photo['original_name'], true);
    }

    /**
     * Règles du formulaire. Types de biens, transactions, villes, communes et titres de propriété sont
     * tous vérifiés en base : aucune valeur ne vient d'une liste figée dans le code.
     *
     * @return array{0: Validator, 1: array<string, mixed>}
     */
    private function validate(Request $request, int $countryId): array
    {
        $input = $request->all();
        foreach (['price', 'living_area', 'land_area'] as $numeric) {
            if (isset($input[$numeric]) && is_string($input[$numeric])) {
                $input[$numeric] = str_replace([' ', "\u{00A0}"], '', $input[$numeric]);
            }
        }
        $v = new Validator($input);
        $v->required('transaction_type_id', 'category_id', 'city_id', 'description')
            ->maxLength('address', 255)
            ->maxLength('description', 5000)
            ->maxLength('conditions', 2000)
            ->decimal('price', 0, 100000000000)
            ->decimal('living_area', 0, 1000000)
            ->decimal('land_area', 0, 100000000)
            ->integer('rooms', 0, 500)
            ->integer('bedrooms', 0, 500)
            ->integer('bathrooms', 0, 500)
            ->in('price_period', ['total', 'month', 'week', 'night', 'year']);

        $options = $this->options($countryId);
        $v->rule('transaction_type_id', isset($options['transactions'][(int) ($input['transaction_type_id'] ?? 0)]), __('validation.in'));
        $categoryIds = [];
        foreach ($options['categories'] as $group) {
            $categoryIds += $group;
        }
        $v->rule('category_id', isset($categoryIds[(int) ($input['category_id'] ?? 0)]), __('validation.in'));
        $v->rule('city_id', isset($options['cities'][(int) ($input['city_id'] ?? 0)]), __('validation.in'));

        $communeId = $v->nullableInt('commune_id');
        if ($communeId !== null) {
            $commune = $this->app->geo()->commune($communeId, $countryId);
            $v->rule('commune_id', $commune !== null && (int) $commune['city_id'] === (int) ($input['city_id'] ?? 0), __('front.partner.commune_invalid'));
        }
        $titleType = trim((string) ($input['title_type'] ?? ''));
        if ($titleType !== '') {
            $v->rule('title_type', isset($options['titles'][$titleType]), __('validation.in'));
        }
        if (mb_strlen(trim((string) ($input['description'] ?? ''))) < self::DESCRIPTION_MIN && !$v->has('description')) {
            $v->add('description', __('owner.submission.description_short', ['min' => self::DESCRIPTION_MIN]));
        }
        $v->rule('consent', !empty($input['consent']), __('owner.submission.consent_required'));

        $data = [
            'transaction_type_id' => (int) $v->int('transaction_type_id'),
            'category_id' => (int) $v->int('category_id'),
            'city_id' => (int) $v->int('city_id'),
            'commune_id' => $communeId,
            'address' => $v->nullableString('address'),
            'description' => trim($v->string('description')),
            'price' => $v->nullableDecimal('price'),
            'price_period' => $v->string('price_period') !== '' ? $v->string('price_period') : 'total',
            'is_negotiable' => $v->bool('is_negotiable') ? 1 : 0,
            'conditions' => $v->nullableString('conditions'),
            'living_area' => $v->nullableDecimal('living_area'),
            'land_area' => $v->nullableDecimal('land_area'),
            'rooms' => $v->nullableInt('rooms'),
            'bedrooms' => $v->nullableInt('bedrooms'),
            'bathrooms' => $v->nullableInt('bathrooms'),
            'title_type' => $titleType !== '' ? $titleType : null,
        ];

        return [$v, $data];
    }

    /**
     * @return array{transactions: array<int, string>, categories: array<string, array<int, string>>, cities: array<int, string>, communes: array<int, string>, titles: array<string, string>}
     */
    private function options(int $countryId): array
    {
        $transactions = [];
        foreach ($this->app->db()->select('SELECT id, name FROM transaction_types WHERE is_active = 1 ORDER BY sort_order, name') as $row) {
            $transactions[(int) $row['id']] = (string) $row['name'];
        }
        $titles = [];
        foreach ($this->app->db()->select(
            "SELECT o.code, o.label FROM property_attribute_options o JOIN property_attributes a ON a.id = o.attribute_id
             WHERE a.code = 'title_type' AND o.is_active = 1 ORDER BY o.sort_order, o.label"
        ) as $row) {
            $titles[(string) $row['code']] = (string) $row['label'];
        }

        return [
            'transactions' => $transactions,
            'categories' => $this->app->catalog()->categoryChoices($countryId),
            'cities' => $this->app->geo()->cityOptions($countryId, true),
            'communes' => $this->app->geo()->communeOptions($countryId),
            'titles' => $titles,
        ];
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed>  $old
     */
    private function form(Request $request, User $user, array $errors = [], array $old = [], int $status = 200): Response
    {
        return $this->page('front/layouts/app', 'front/pages/owner/submission-form', $this->options($this->site()->country->id) + [
            'user' => $user,
            'errors' => $errors,
            'old' => $old + ['price_period' => 'total'],
            'flash' => $this->app->session()->flashes(),
            'csrfToken' => $this->app->csrf()->token(),
            'limits' => ['photos' => self::MAX_PHOTOS, 'documents' => self::MAX_DOCUMENTS, 'photo_mb' => self::MAX_PHOTO_MB, 'document_mb' => self::MAX_DOCUMENT_MB],
        ], ['title' => __('owner.submission.form_title'), 'description' => __('owner.meta_description', ['site' => $this->site()->name]), 'noindex' => true], $status);
    }

    private function owner(Request $request): User
    {
        $user = $request->attribute('owner');

        return $user instanceof User ? $user : throw new \LogicException('Route sans AuthenticateOwner.');
    }
}
