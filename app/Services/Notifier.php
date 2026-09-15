<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Core\View;
use App\Models\Site;
use Throwable;

/**
 * Notifications du back-office (table notifications, cloche de la barre supérieure) doublées d'un email.
 * Un échec d'envoi d'email est journalisé sans bloquer l'action qui l'a déclenché.
 */
final class Notifier
{
    public function __construct(
        private readonly Database $db,
        private readonly Mailer $mailer,
        private readonly View $view,
        private readonly Logger $logger,
    ) {
    }

    /**
     * @param list<int>   $userIds  Destinataires (doublons et comptes inactifs ignorés)
     * @param string      $link     Chemin interne du back-office (/cmsadmin/…)
     * @param list<int>   $emailTo  Destinataires à prévenir aussi par email (sous-ensemble de $userIds)
     */
    public function notify(array $userIds, string $type, string $title, ?string $body, string $link, ?Site $site, array $emailTo = []): void
    {
        $recipients = $this->activeUsers($userIds);
        foreach ($recipients as $user) {
            $this->db->insert('notifications', [
                'user_id' => (int) $user['id'],
                'type' => $type,
                'title' => mb_substr($title, 0, 190),
                'body' => $body !== null ? mb_substr($body, 0, 500) : null,
                'link_url' => mb_substr($link, 0, 255),
            ]);
        }

        if ($site === null) {
            return;
        }
        foreach ($recipients as $user) {
            if (!in_array((int) $user['id'], $emailTo, true)) {
                continue;
            }
            $data = ['site' => $site, 'name' => (string) $user['first_name'], 'title' => $title, 'body' => $body, 'link' => absolute_url($link)];
            try {
                $this->mailer->send(
                    (string) $user['email'],
                    $title . ' · ' . $site->name,
                    $this->view->page('emails/layout', 'emails/notification', $data, ['site' => $site, 'preheader' => (string) $body]),
                    $this->view->render('emails/notification.text', $data),
                    trim($user['first_name'] . ' ' . $user['last_name'])
                );
            } catch (Throwable $exception) {
                $this->logger->exception($exception, ['notification' => $type, 'user_id' => $user['id']]);
            }
        }
    }

    /**
     * Équipe du pays : Admins Pays du pays et Super Admins (actifs).
     *
     * @return array{all: list<int>, email: list<int>} email = Admins Pays, ou Super Admins s'il n'y en a aucun
     */
    public function staffRecipients(int $countryId): array
    {
        $rows = $this->db->select(
            "SELECT id, role FROM users WHERE is_active = 1 AND deleted_at IS NULL
               AND (role = 'super_admin' OR (role = 'country_admin' AND country_id = :country))",
            ['country' => $countryId]
        );
        $countryAdmins = array_map('intval', array_column(array_filter($rows, static fn (array $r): bool => $r['role'] === 'country_admin'), 'id'));
        $all = array_map('intval', array_column($rows, 'id'));

        return ['all' => $all, 'email' => $countryAdmins !== [] ? $countryAdmins : $all];
    }

    /**
     * Personnes concernées par une annonce d'agence : responsables de l'agence, agent en charge, auteur.
     *
     * @param array<string, mixed> $property
     * @return list<int>
     */
    public function propertyRecipients(array $property): array
    {
        $ids = array_filter([(int) ($property['created_by_user_id'] ?? 0), (int) ($property['agent_user_id'] ?? 0)]);
        if (!empty($property['agency_id'])) {
            $owners = $this->db->select(
                "SELECT id FROM users WHERE agency_id = :agency AND role = 'agency_owner' AND is_active = 1 AND deleted_at IS NULL",
                ['agency' => $property['agency_id']]
            );
            $ids = [...$ids, ...array_map('intval', array_column($owners, 'id'))];
        }

        return array_values(array_unique($ids));
    }

    /** @return list<array<string, mixed>> */
    public function latest(int $userId, int $limit = 6): array
    {
        return $this->db->select(
            'SELECT id, type, title, body, link_url, read_at, created_at FROM notifications WHERE user_id = :user ORDER BY read_at IS NULL DESC, created_at DESC LIMIT :limit',
            ['user' => $userId, 'limit' => $limit]
        );
    }

    public function unreadCount(int $userId): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM notifications WHERE user_id = :user AND read_at IS NULL', ['user' => $userId]);
    }

    /** @return array<string, mixed>|null */
    public function open(int $notificationId, int $userId): ?array
    {
        $notification = $this->db->selectOne('SELECT * FROM notifications WHERE id = :id AND user_id = :user', ['id' => $notificationId, 'user' => $userId]);
        if ($notification !== null && $notification['read_at'] === null) {
            $this->db->execute('UPDATE notifications SET read_at = UTC_TIMESTAMP() WHERE id = :id', ['id' => $notificationId]);
        }

        return $notification;
    }

    public function markAllRead(int $userId): void
    {
        $this->db->execute('UPDATE notifications SET read_at = UTC_TIMESTAMP() WHERE user_id = :user AND read_at IS NULL', ['user' => $userId]);
    }

    /**
     * @param list<int> $userIds
     * @return list<array<string, mixed>>
     */
    private function activeUsers(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if ($userIds === []) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($userIds), '?'));

        return $this->db->select(
            "SELECT u.id, u.first_name, u.last_name, u.email FROM users u LEFT JOIN agencies a ON a.id = u.agency_id
             WHERE u.id IN ({$placeholders}) AND u.is_active = 1 AND u.deleted_at IS NULL
               AND (u.agency_id IS NULL OR (a.status = 'active' AND a.deleted_at IS NULL))",
            $userIds
        );
    }
}
