<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\Controller as BaseController;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Models\Site;
use App\Services\IpAddress;
use App\Support\Validator;

/**
 * Base des contrôleurs du site public : garde-fous communs aux formulaires sans compte.
 *
 * Tout formulaire public suit le même gabarit (posé au lot 1.10, généralisé au lot 1.11) :
 * jeton CSRF, pot de miel, limitation par adresse IP, consentement obligatoire, validation avec
 * réaffichage en 422, écriture d'un lead puis notification du back-office.
 */
abstract class Controller extends BaseController
{
    /** Nom du champ piège : invisible pour un visiteur, rempli par un robot. */
    protected const HONEYPOT = 'site_web';

    /** Envois acceptés par adresse IP sur une heure glissante, tous formulaires confondus. */
    protected const MAX_SUBMISSIONS = 5;
    protected const WINDOW = 3600;

    /** Site courant, ou 404 : aucune page publique n'existe hors d'un site résolu. */
    protected function site(): Site
    {
        return site() ?? throw new HttpException(404);
    }

    /** Un robot a rempli le champ piège : l'envoi est ignoré, sans le lui dire. */
    protected function isTrapped(Request $request): bool
    {
        return trim((string) $request->input(self::HONEYPOT, '')) !== '';
    }

    /** false quand l'adresse IP a dépassé son quota d'envois. */
    protected function withinQuota(Request $request, string $form): bool
    {
        return $this->app->rateLimiter()->attempt(
            "public-form:{$form}:" . $request->ip(),
            static::MAX_SUBMISSIONS,
            static::WINDOW
        );
    }

    /**
     * Règles communes aux formulaires de contact : identité, moyen de recontact, message, consentement.
     *
     * @param array<string, mixed> $input
     */
    protected function validateContact(array $input, bool $phoneRequired = false): Validator
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
        if ($phoneRequired) {
            $validator->required('phone');
        }

        // `leads` impose au moins un moyen de recontact (contrainte chk_leads_contact).
        $validator->rule(
            'email',
            trim((string) ($input['email'] ?? '')) !== '' || trim((string) ($input['phone'] ?? '')) !== '',
            __('front.contact.contact_required')
        );
        $validator->rule('consent', !empty($input['consent']), __('front.contact.consent_required'));

        return $validator;
    }

    /**
     * Enregistre une demande dans `leads`. Le site, le pays, le consentement et les traces
     * techniques sont ajoutés ici : un contrôleur ne fournit que le métier.
     *
     * @param array<string, mixed> $data type, property_id, agency_id, name, email, phone, message, payload
     */
    protected function insertLead(Request $request, array $data): int
    {
        $site = $this->site();

        return $this->app->db()->insert('leads', $data + [
            'site_id' => $site->id,
            'country_id' => $site->country->id,
            'consent_at' => gmdate('Y-m-d H:i:s'),
            'source_url' => mb_substr(absolute_url(ltrim($request->path(), '/')), 0, 255),
            'ip' => IpAddress::toBinary($request->ip()),
            'user_agent' => mb_substr($request->userAgent(), 0, 255),
        ]);
    }

    /**
     * Prévient des destinataires du back-office (cloche + email).
     *
     * @param list<int> $recipients Vide : l'équipe du pays est prévenue
     */
    protected function notifyBackOffice(string $type, string $title, ?string $body, string $link, array $recipients = []): void
    {
        if ($recipients === []) {
            $recipients = $this->app->notifier()->staffRecipients($this->site()->country->id)['all'];
        }
        if ($recipients === []) {
            return;
        }

        $this->app->notifier()->notify($recipients, $type, $title, $body, $link, site(), $recipients);
    }
}
