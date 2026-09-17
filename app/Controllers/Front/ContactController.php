<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Request;
use App\Core\Response;
use App\Services\IpAddress;
use App\Services\PrivateFiles;
use App\Support\Validator;
use Throwable;

/**
 * Formulaires publics autonomes : Contact et Devenir partenaire.
 *
 * Tous suivent le gabarit du lot 1.10 (jeton CSRF, pot de miel, limite par adresse IP,
 * consentement obligatoire, réaffichage en 422, succès en 303) hérité de Front\Controller.
 * Aucun ne crée de compte : « Devenir partenaire » dépose un dossier que Weblogy étudie depuis le
 * back-office avant d'ouvrir la console du partenaire. Un particulier, lui, confie son bien depuis
 * l'espace propriétaire (Owner\SubmissionController).
 */
final class ContactController extends Controller
{
    /** Objets proposés dans le formulaire de contact général. */
    private const SUBJECTS = ['achat', 'location', 'partenariat', 'technique', 'autre'];

    /** Fourchettes d'annonces proposées à un partenaire candidat. */
    private const VOLUMES = [5, 15, 30, 60, 100];

    /** Professionnels admis à devenir partenaires (colonne `partner_type`). */
    public const PARTNER_TYPES = ['agency', 'developer', 'property_manager', 'other'];

    /** Formes juridiques proposées (code stocké, libellé traduit). */
    public const LEGAL_FORMS = ['sarl', 'suarl', 'sa', 'sas', 'sasu', 'ei', 'gie', 'other'];

    /** Pièces justificatives du dossier : champ du formulaire => type en base et caractère obligatoire. */
    private const PARTNER_DOCUMENTS = [
        'doc_rccm' => ['kind' => 'rccm', 'required' => true],
        'doc_identity' => ['kind' => 'identity', 'required' => true],
        'doc_tax' => ['kind' => 'tax', 'required' => false],
        'doc_license' => ['kind' => 'license', 'required' => false],
    ];

    private const MAX_DOCUMENT_MB = 5;

    // -- Contact général -------------------------------------------------------------------

    public function contact(Request $request): Response
    {
        return $this->renderContact($request);
    }

    public function sendContact(Request $request): Response
    {
        $input = $request->all();
        $validator = $this->validateContact($input);
        $validator->in('sujet', self::SUBJECTS);

        if ($validator->fails()) {
            return $this->renderContact($request, $validator->errors(), $input, 422);
        }
        if (!$this->withinQuota($request, 'contact')) {
            return $this->renderContact($request, ['message' => __('front.contact.too_many')], $input, 429);
        }

        if (!$this->isTrapped($request)) {
            $subject = $validator->string('sujet') !== '' ? $validator->string('sujet') : 'autre';
            $leadId = $this->insertLead($request, [
                'type' => 'general_contact',
                'name' => $validator->string('name'),
                'email' => $validator->nullableString('email'),
                'phone' => $validator->nullableString('phone'),
                'message' => $validator->nullableString('message'),
                'payload' => json_encode(['sujet' => $subject], JSON_UNESCAPED_UNICODE),
            ]);

            $this->notifyBackOffice(
                'lead.new',
                __('front.contact.notification_title_general', ['subject' => __('front.contact.subjects.' . $subject)]),
                __('front.contact.notification_body_simple', ['name' => $validator->string('name')]),
                cmsadmin_url('contacts/' . $leadId)
            );
        }

        $this->flash('success', __('front.contact.success_general'));

        return $this->redirect('contact', 303);
    }

    // -- Devenir agence partenaire ----------------------------------------------------------

    public function partner(Request $request): Response
    {
        return $this->renderPartner($request);
    }

    public function sendPartner(Request $request): Response
    {
        $site = $this->site();
        $input = $request->all();
        $validator = $this->validatePartner($input, $site->country->id);

        // Pièces justificatives : contrôlées avant tout enregistrement (type réel, taille).
        $files = $this->app->privateFiles();
        $documents = [];
        foreach (self::PARTNER_DOCUMENTS as $name => $definition) {
            $file = PrivateFiles::normalize($request->file($name))[0] ?? null;
            if ($file === null) {
                if ($definition['required']) {
                    $validator->add($name, __('front.partner.documents.required'));
                }
                continue;
            }
            $error = $files->checkDocument($file, self::MAX_DOCUMENT_MB * 1048576);
            if ($error !== null) {
                $validator->add($name, __($error, ['max' => self::MAX_DOCUMENT_MB . ' Mo']));
                continue;
            }
            $documents[$name] = $file;
        }

        if ($validator->fails()) {
            return $this->renderPartner($request, $validator->errors(), $input, 422);
        }
        if (!$this->withinQuota($request, 'partner')) {
            return $this->renderPartner($request, ['message' => __('front.contact.too_many')], $input, 429);
        }

        if (!$this->isTrapped($request)) {
            $website = $validator->nullableString('website');
            if ($website !== null && preg_match('#^https?://#i', $website) !== 1) {
                $website = 'https://' . $website;
            }
            $stored = [];

            try {
                $requestId = $this->app->db()->transaction(function () use ($site, $request, $validator, $website, $documents, $files, &$stored): int {
                    $requestId = $this->app->db()->insert('partner_requests', [
                        'site_id' => $site->id,
                        'country_id' => $site->country->id,
                        'partner_type' => $validator->string('partner_type'),
                        'agency_name' => $validator->string('agency_name'),
                        'legal_name' => $validator->nullableString('legal_name'),
                        'legal_form' => $validator->nullableString('legal_form'),
                        'contact_name' => $validator->string('name'),
                        'contact_role' => $validator->nullableString('contact_role'),
                        'email' => mb_strtolower($validator->string('email')),
                        'phone' => $validator->string('phone'),
                        'company_email' => $validator->nullableString('company_email') !== null ? mb_strtolower($validator->string('company_email')) : null,
                        'company_phone' => $validator->nullableString('company_phone'),
                        'website' => $website,
                        'address' => $validator->nullableString('address'),
                        'rccm' => $validator->string('rccm'),
                        'tax_id' => $validator->nullableString('tax_id'),
                        'professional_card' => $validator->nullableString('professional_card'),
                        'city_id' => $validator->nullableInt('city_id'),
                        'commune_id' => $validator->nullableInt('commune_id'),
                        'listings_estimate' => $validator->nullableInt('listings_estimate'),
                        'years_active' => $validator->nullableInt('years_active'),
                        'message' => $validator->nullableString('message'),
                        'consent_at' => gmdate('Y-m-d H:i:s'),
                        'ip' => IpAddress::toBinary($request->ip()),
                    ]);

                    $directory = strtolower($site->country->iso2) . '/partenariats/' . $requestId;
                    foreach ($documents as $name => $file) {
                        $saved = $files->storeDocument($file, $directory);
                        $stored[] = $saved['path'];
                        $this->app->db()->insert('partner_request_files', $saved + [
                            'partner_request_id' => $requestId,
                            'kind' => self::PARTNER_DOCUMENTS[$name]['kind'],
                        ]);
                    }

                    return $requestId;
                });
            } catch (Throwable $exception) {
                // La demande n'est pas enregistrée : les fichiers déjà écrits ne doivent pas rester orphelins.
                foreach ($stored as $path) {
                    $files->delete($path);
                }
                $this->app->logger()->exception($exception, ['form' => 'partner']);

                return $this->renderPartner($request, ['message' => __('front.partner.save_failed')], $input, 500);
            }

            $this->notifyBackOffice(
                'partner_request.new',
                __('front.partner.notification_title', ['agency' => $validator->string('agency_name')]),
                __('front.partner.notification_body', ['name' => $validator->string('name'), 'email' => $validator->string('email')]),
                cmsadmin_url('demandes-partenariat/' . $requestId)
            );
        }

        $this->flash('success', __('front.partner.success'));

        return $this->redirect('devenir-partenaire', 303);
    }

    // -- Rendus -----------------------------------------------------------------------------

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed>  $old
     */
    private function renderContact(Request $request, array $errors = [], array $old = [], int $status = 200): Response
    {
        $site = $this->site();

        return $this->form('front/pages/contact', [
            'subjects' => self::SUBJECTS,
            'site' => $site,
        ], $errors, $old, $status, [
            'title' => __('front.contact.page_title'),
            'description' => __('front.contact.page_description', ['site' => $site->name]),
            'canonical' => absolute_url('contact'),
            // Leaflet n'est chargé que si le site a une position à montrer.
            'pageScripts' => $site->hasLocation() ? ['vendors/leaflet/leaflet.js', 'js/contact.js'] : [],
            'pageStyles' => $site->hasLocation() ? ['vendors/leaflet/leaflet.css'] : [],
        ]);
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed>  $old
     */
    private function renderPartner(Request $request, array $errors = [], array $old = [], int $status = 200): Response
    {
        $countryId = $this->site()->country->id;

        return $this->form('front/pages/partner', [
            'cities' => $this->app->geo()->cityOptions($countryId, true),
            'communes' => $this->app->geo()->communeOptions($countryId),
            'volumes' => self::VOLUMES,
            'types' => self::PARTNER_TYPES,
            'legalForms' => array_combine(self::LEGAL_FORMS, array_map(static fn (string $form): string => __('front.partner.legal_forms.' . $form), self::LEGAL_FORMS)),
            'documents' => array_map(static fn (array $document): array => ['required' => $document['required']], self::PARTNER_DOCUMENTS),
            'maxMb' => self::MAX_DOCUMENT_MB,
        ], $errors, $old, $status, [
            'title' => __('front.partner.page_title'),
            'description' => __('front.partner.page_description'),
            'canonical' => absolute_url('devenir-partenaire'),
        ]);
    }

    /**
     * Rendu commun des trois formulaires : données de la vue, erreurs, valeurs ressaisies et
     * message de confirmation.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $errors
     * @param array<string, mixed>  $old
     * @param array<string, mixed>  $layout
     */
    private function form(string $view, array $data, array $errors, array $old, int $status, array $layout): Response
    {
        return $this->page('front/layouts/app', $view, $data + [
            'errors' => $errors,
            'old' => $old,
            'flash' => $this->app->session()->flashes(),
            'csrfToken' => $this->app->csrf()->token(),
        ], $layout, $status);
    }

    /**
     * Règles propres à la demande de partenariat : l'agence, un contact joignable et une ville
     * du pays du site.
     *
     * @param array<string, mixed> $input
     */
    private function validatePartner(array $input, int $countryId): Validator
    {
        $validator = new Validator($input);
        $validator->required('partner_type', 'agency_name', 'rccm', 'city_id', 'name', 'email', 'phone')
            ->in('partner_type', self::PARTNER_TYPES)
            ->maxLength('agency_name', 150)
            ->maxLength('legal_name', 190)
            ->maxLength('rccm', 60)
            ->maxLength('tax_id', 60)
            ->maxLength('professional_card', 60)
            ->maxLength('company_email', 190)->email('company_email')
            ->maxLength('company_phone', 30)->phone('company_phone')
            ->maxLength('website', 255)
            ->maxLength('address', 255)
            ->maxLength('name', 150)
            ->maxLength('contact_role', 100)
            ->maxLength('email', 190)->email('email')
            ->maxLength('phone', 30)->phone('phone')
            ->maxLength('message', 2000);

        if (!empty($input['legal_form'])) {
            $validator->in('legal_form', self::LEGAL_FORMS);
        }
        $website = trim((string) ($input['website'] ?? ''));
        if ($website !== '') {
            $candidate = preg_match('#^https?://#i', $website) === 1 ? $website : 'https://' . $website;
            $validator->rule('website', filter_var($candidate, FILTER_VALIDATE_URL) !== false, __('validation.url'));
        }

        $geo = $this->app->geo();
        $cities = $geo->cityOptions($countryId, true);
        if (!empty($input['city_id'])) {
            $validator->rule('city_id', isset($cities[(int) $input['city_id']]), __('front.partner.city_invalid'));
        }
        if (!empty($input['commune_id'])) {
            $commune = $geo->commune((int) $input['commune_id'], $countryId);
            $validator->rule('commune_id', $commune !== null && (int) $commune['city_id'] === (int) ($input['city_id'] ?? 0), __('front.partner.commune_invalid'));
        }
        if (!empty($input['listings_estimate'])) {
            $validator->integer('listings_estimate', 1, 10000);
        }
        if (($input['years_active'] ?? '') !== '') {
            $validator->integer('years_active', 0, 150);
        }
        $validator->rule('consent', !empty($input['consent']), __('front.contact.consent_required'));

        return $validator;
    }
}
