<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Lecture du journal d'activité (`activity_logs`), écran « Journal d'activité » du back-office.
 *
 * L'écriture reste à `ActivityLogger` : ce dépôt ne fait que lire, et **rien ici ne modifie ni ne
 * supprime une ligne**. Un journal dont on peut effacer une entrée depuis l'interface ne prouve
 * plus rien : la purge éventuelle des vieilles lignes se fera en base, jamais depuis le site.
 *
 * Périmètre : un Admin Pays ne voit que son pays ; un Super Admin voit tout, y compris les actions
 * sans pays (`country_id IS NULL`, actions système).
 */
final class ActivityRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param array{q?: string, action?: string, module?: string, utilisateur?: string, du?: string, au?: string} $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function paginate(?int $countryId, array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->conditions($countryId, $filters);
        $from = "FROM activity_logs l
                 LEFT JOIN users u ON u.id = l.user_id
                 LEFT JOIN countries c ON c.id = l.country_id
                 WHERE {$where}";

        return [
            'total' => (int) $this->db->scalar("SELECT COUNT(*) {$from}", $params),
            'rows' => $this->db->select(
                "SELECT l.id, l.action, l.entity_type, l.entity_id, l.description, l.changes,
                        l.ip, l.user_agent, l.created_at,
                        CONCAT(u.first_name, ' ', u.last_name) AS user_name, u.email AS user_email,
                        u.role AS user_role, c.name AS country_name
                 {$from}
                 ORDER BY l.created_at DESC, l.id DESC
                 LIMIT :limit OFFSET :offset",
                $params + ['limit' => $limit, 'offset' => $offset]
            ),
        ];
    }

    /**
     * Actions présentes dans le journal, pour alimenter le filtre.
     *
     * La liste est construite depuis les données et non figée dans le code : un nouveau verbe
     * journalisé apparaît dans le filtre sans qu'on ait à y penser.
     *
     * @return list<string>
     */
    public function actions(?int $countryId): array
    {
        [$where, $params] = $this->conditions($countryId, []);

        return array_map(
            static fn (array $row): string => (string) $row['action'],
            $this->db->select("SELECT DISTINCT l.action FROM activity_logs l WHERE {$where} ORDER BY l.action", $params)
        );
    }

    /**
     * Comptes ayant laissé une trace, pour alimenter le filtre.
     *
     * @return list<array<string, mixed>>
     */
    public function users(?int $countryId): array
    {
        [$where, $params] = $this->conditions($countryId, []);

        return $this->db->select(
            "SELECT DISTINCT l.user_id, CONCAT(u.first_name, ' ', u.last_name) AS name, u.email
             FROM activity_logs l JOIN users u ON u.id = l.user_id
             WHERE {$where} ORDER BY name",
            $params
        );
    }

    /**
     * Dernières actions sur une entité précise (annonce, agence…), pour un encart d'historique.
     *
     * @return list<array<string, mixed>>
     */
    public function forEntity(string $entityType, int $entityId, int $limit = 20): array
    {
        return $this->db->select(
            "SELECT l.action, l.description, l.changes, l.created_at,
                    CONCAT(u.first_name, ' ', u.last_name) AS user_name
             FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id
             WHERE l.entity_type = :type AND l.entity_id = :id
             ORDER BY l.created_at DESC LIMIT :limit",
            ['type' => $entityType, 'id' => $entityId, 'limit' => $limit]
        );
    }

    /**
     * Conditions communes. Le pays est imposé par l'appelant, jamais lu dans la requête HTTP.
     *
     * @param array{q?: string, action?: string, module?: string, utilisateur?: string, du?: string, au?: string} $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function conditions(?int $countryId, array $filters): array
    {
        $where = '1 = 1';
        $params = [];

        if ($countryId !== null) {
            $where .= ' AND l.country_id = :country';
            $params['country'] = $countryId;
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $where .= ' AND (l.description LIKE :q OR l.action LIKE :q2 OR u.email LIKE :q3)';
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $params += ['q' => $like, 'q2' => $like, 'q3' => $like];
        }

        $action = trim((string) ($filters['action'] ?? ''));
        if ($action !== '') {
            $where .= ' AND l.action = :action';
            $params['action'] = $action;
        }

        // « Module » = ce qui précède le point (property, agency, user…) : filtrer sur la famille
        // d'actions est plus utile au quotidien que de choisir un verbe précis.
        $module = trim((string) ($filters['module'] ?? ''));
        if ($module !== '' && preg_match('/^[a-z_]+$/', $module) === 1) {
            $where .= ' AND l.action LIKE :module';
            $params['module'] = $module . '.%';
        }

        $userId = (int) ($filters['utilisateur'] ?? 0);
        if ($userId > 0) {
            $where .= ' AND l.user_id = :user';
            $params['user'] = $userId;
        }

        foreach (['du' => '>=', 'au' => '<'] as $key => $operator) {
            $date = trim((string) ($filters[$key] ?? ''));
            if ($date === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
                continue;
            }
            // « au » est inclusif pour l'utilisateur : on compare au lendemain à minuit.
            $where .= " AND l.created_at {$operator} :date_{$key}";
            $params["date_{$key}"] = $key === 'au' ? date('Y-m-d', strtotime($date . ' +1 day')) : $date;
        }

        return [$where, $params];
    }
}
