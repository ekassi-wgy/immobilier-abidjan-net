<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Core\View;
use App\Models\Site;
use App\Models\User;
use Throwable;

/**
 * Emails aux particuliers (espace propriétaire) : confirmation d'adresse et suivi des biens confiés.
 *
 * Ils reprennent le gabarit des notifications, avec un bouton et un pied de message propres à
 * l'espace propriétaire. Un échec d'envoi est journalisé sans interrompre l'action de l'utilisateur,
 * sauf la confirmation d'adresse, dont l'appelant doit savoir qu'elle n'est pas partie.
 */
final class OwnerMessages
{
    /** Validité du lien de confirmation d'adresse. */
    private const VERIFY_HOURS = 48;

    /** Délai minimal entre deux liens de confirmation pour un même compte (anti-abus d'envoi). */
    private const RESEND_MINUTES = 2;

    public function __construct(
        private readonly Database $db,
        private readonly Mailer $mailer,
        private readonly View $view,
        private readonly Logger $logger,
    ) {
    }

    /**
     * Envoie un lien de confirmation d'adresse (le précédent est remplacé).
     *
     * @return bool false si un lien vient d'être envoyé ou si l'email n'a pas pu partir
     */
    public function sendVerification(User $user, Site $site): bool
    {
        $recent = $this->db->scalar(
            'SELECT 1 FROM email_verifications WHERE user_id = :id AND created_at >= UTC_TIMESTAMP() - INTERVAL :minutes MINUTE',
            ['id' => $user->id, 'minutes' => self::RESEND_MINUTES]
        );
        if ($recent !== null) {
            return false;
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->db->execute(
            'REPLACE INTO email_verifications (user_id, token_hash, expires_at, created_at)
             VALUES (:id, :hash, UTC_TIMESTAMP() + INTERVAL :hours HOUR, UTC_TIMESTAMP())',
            ['id' => $user->id, 'hash' => hash('sha256', $token), 'hours' => self::VERIFY_HOURS]
        );

        return $this->send(
            $user,
            $site,
            __('owner.verify.email_subject', ['site' => $site->name]),
            __('owner.verify.email_title'),
            __('owner.verify.email_body', ['hours' => self::VERIFY_HOURS]),
            absolute_url('mon-espace/confirmer-email/' . $token),
            __('owner.verify.email_button')
        );
    }

    /** Confirme l'adresse du compte associé au jeton. Retourne l'identifiant du compte, ou null si le lien n'est plus valide. */
    public function confirm(string $token): ?int
    {
        if (preg_match('/^[A-Za-z0-9_-]{43}$/', $token) !== 1) {
            return null;
        }
        $userId = $this->db->scalar(
            'SELECT user_id FROM email_verifications WHERE token_hash = :hash AND expires_at > UTC_TIMESTAMP()',
            ['hash' => hash('sha256', $token)]
        );
        if ($userId === null) {
            return null;
        }

        $this->db->transaction(function (Database $db) use ($userId): void {
            $db->execute('UPDATE users SET email_verified_at = COALESCE(email_verified_at, UTC_TIMESTAMP()) WHERE id = :id', ['id' => $userId]);
            $db->execute('DELETE FROM email_verifications WHERE user_id = :id', ['id' => $userId]);
        });

        return (int) $userId;
    }

    /** Message de suivi d'un bien confié (reçu, refusé, en ligne…). */
    public function send(User $user, Site $site, string $subject, string $title, ?string $body, string $link, ?string $button = null): bool
    {
        $data = [
            'site' => $site,
            'name' => $user->firstName,
            'title' => $title,
            'body' => $body,
            'link' => $link,
            'button' => $button ?? __('owner.email_button'),
            'footer' => __('owner.email_footer', ['site' => $site->name]),
        ];

        try {
            $this->mailer->send(
                $user->email,
                $subject,
                $this->view->page('emails/layout', 'emails/notification', $data, ['site' => $site, 'preheader' => (string) $body]),
                $this->view->render('emails/notification.text', $data),
                $user->fullName()
            );

            return true;
        } catch (Throwable $exception) {
            $this->logger->exception($exception, ['owner_email' => $user->id]);

            return false;
        }
    }
}
