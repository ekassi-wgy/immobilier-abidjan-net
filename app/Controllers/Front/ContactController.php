<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Request;
use App\Core\Response;
use App\Services\IpAddress;
use App\Support\Validator;

/**
 * Formulaires publics autonomes (lot 1.11) : Contact, Devenir partenaire, Déposer un bien.
 *
 * Tous suivent le gabarit du lot 1.10 (jeton CSRF, pot de miel, limite par adresse IP,
 * consentement obligatoire, réaffichage en 422, succès en 303) hérité de Front\Controller.
 * Aucun de ces formulaires ne crée de compte : « Devenir partenaire » dépose une demande que
 * l'équipe traite depuis le back-office, « Déposer un bien » arrive en demande de contact.
 */
final class ContactController extends Controller
{
    /** Objets proposés dans le formulaire de contact général. */
    private const SUBJECTS = ['achat', 'location', 'partenariat', 'technique', 'autre'];

    /** Fourchettes d'annonces proposées à une agence candidate. */
    private const VOLUMES = [5, 15, 30, 60, 100];

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

        if ($validator->fails()) {
            return $this->renderPartner($request, $validator->errors(), $input, 422);
        }
        if (!$this->withinQuota($request, 'partner')) {
            return $this->renderPartner($request, ['message' => __('front.contact.too_many')], $input, 429);
        }

        if (!$this->isTrapped($request)) {
            $requestId = $this->app->db()->insert('partner_requests', [
                'site_id' => $site->id,
                'country_id' => $site->country->id,
                'agency_name' => $validator->string('agency_name'),
                'contact_name' => $validator->string('name'),
                'email' => $validator->string('email'),
                'phone' => $validator->string('phone'),
                'rccm' => $validator->nullableString('rccm'),
                'city_id' => $validator->nullableInt('city_id'),
                'commune_id' => $validator->nullableInt('commune_id'),
                'listings_estimate' => $validator->nullableInt('listings_estimate'),
                'message' => $validator->nullableString('message'),
                'consent_at' => gmdate('Y-m-d H:i:s'),
                'ip' => IpAddress::toBinary($request->ip()),
            ]);

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

    // -- Déposer un bien --------------------------------------------------------------------

    public function submit(Request $request): Response
    {
        return $this->renderSubmit($request);
    }

    public function sendSubmit(Request $request): Response
    {
        $site = $this->site();
        $input = $request->all();
        $options = $this->submitOptions($site->country->id);
        $validator = $this->validateContact($input, phoneRequired: true);
        $validator->required('transaction', 'type')
            ->in('transaction', array_column($options['transactions'], 'slug'))
            ->in('type', array_keys($options['types']));

        if ($validator->fails()) {
            return $this->renderSubmit($request, $validator->errors(), $input, 422);
        }
        if (!$this->withinQuota($request, 'submission')) {
            return $this->renderSubmit($request, ['message' => __('front.contact.too_many')], $input, 429);
        }

        if (!$this->isTrapped($request)) {
            $payload = [
                'transaction' => $validator->string('transaction'),
                'type' => $validator->string('type'),
                'commune' => $validator->nullableString('commune'),
                'surface' => $validator->nullableString('surface'),
                'prix' => $validator->nullableString('prix'),
            ];

            $leadId = $this->insertLead($request, [
                'type' => 'property_submission',
                'name' => $validator->string('name'),
                'email' => $validator->nullableString('email'),
                'phone' => $validator->nullableString('phone'),
                'message' => $validator->nullableString('message'),
                'payload' => json_encode(array_filter($payload), JSON_UNESCAPED_UNICODE),
            ]);

            $this->notifyBackOffice(
                'lead.new',
                __('front.submit.notification_title'),
                __('front.submit.notification_body', [
                    'name' => $validator->string('name'),
                    'type' => $options['types'][$validator->string('type')] ?? $validator->string('type'),
                ]),
                cmsadmin_url('contacts/' . $leadId)
            );
        }

        $this->flash('success', __('front.submit.success'));

        return $this->redirect('deposer-un-bien', 303);
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
        ]);
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed>  $old
     */
    private function renderPartner(Request $request, array $errors = [], array $old = [], int $status = 200): Response
    {
        $countryId = $this->site()->country->id;
        $cityId = (int) ($old['city_id'] ?? 0);

        return $this->form('front/pages/partner', [
            'cities' => $this->app->geo()->cityOptions($countryId, true),
            'communes' => $cityId > 0 ? $this->app->geo()->communeOptions($countryId, $cityId) : [],
            'volumes' => self::VOLUMES,
        ], $errors, $old, $status, [
            'title' => __('front.partner.page_title'),
            'description' => __('front.partner.page_description'),
            'canonical' => absolute_url('devenir-partenaire'),
        ]);
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed>  $old
     */
    private function renderSubmit(Request $request, array $errors = [], array $old = [], int $status = 200): Response
    {
        $options = $this->submitOptions($this->site()->country->id);

        return $this->form('front/pages/submit-property', $options, $errors, $old, $status, [
            'title' => __('front.submit.page_title'),
            'description' => __('front.submit.page_description'),
            'canonical' => absolute_url('deposer-un-bien'),
        ]);
    }

    /**
     * Options du formulaire « Déposer un bien » : transactions actives et types de biens à plat.
     *
     * @return array{transactions: list<array{slug: string, label: string}>, types: array<string, string>, groups: array<string, array<string, string>>}
     */
    private function submitOptions(int $countryId): array
    {
        $search = $this->app->searchOptions();
        $groups = $search->propertyTypes($countryId);
        $types = [];
        foreach ($groups as $family) {
            foreach ($family as $slug => $label) {
                $types[$slug] = $label;
            }
        }

        return [
            'transactions' => array_map(
                static fn (array $t): array => ['slug' => $t['slug'], 'label' => $t['label']],
                $search->transactions()
            ),
            'types' => $types,
            'groups' => $groups,
        ];
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
        $validator->required('agency_name', 'name', 'email', 'phone')
            ->maxLength('agency_name', 150)
            ->maxLength('name', 150)
            ->maxLength('email', 190)
            ->maxLength('phone', 30)
            ->maxLength('rccm', 60)
            ->maxLength('message', 2000)
            ->email('email')
            ->phone('phone');

        $cities = $this->app->geo()->cityOptions($countryId, true);
        if (!empty($input['city_id'])) {
            $validator->rule('city_id', isset($cities[(int) $input['city_id']]), __('front.partner.city_invalid'));
        }
        if (!empty($input['listings_estimate'])) {
            $validator->integer('listings_estimate', 1, 10000);
        }
        $validator->rule('consent', !empty($input['consent']), __('front.contact.consent_required'));

        return $validator;
    }
}
