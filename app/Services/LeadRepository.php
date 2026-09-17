<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Demandes de contact reçues des visiteurs (table leads) : messages sur une annonce, sur une agence,
 * contact général et dépôts de bien. Les formulaires publics qui les créent arrivent au lot 1.11.
 *
 * Toutes les lectures sont limitées au pays du site, et à l'agence destinataire pour un compte agence.
 */
final class LeadRepository
{
    public const STATUSES = ['new', 'read', 'in_progress', 'closed', 'spam'];
    public const TYPES = ['property_contact', 'agency_contact', 'general_contact', 'property_submission'];

    /** Types qu'une agence peut recevoir : ses annonces et sa page agence. */
    public const AGENCY_TYPES = ['property_contact', 'agency_contact'];

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param array{q?: string, statut?: string, type?: string, periode?: string} $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function paginate(int $countryId, ?int $agencyId, array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->conditions($countryId, $agencyId, $filters);
        $from = "FROM leads l
                 LEFT JOIN properties p ON p.id = l.property_id
                 LEFT JOIN agencies a ON a.id = l.agency_id
                 LEFT JOIN users u ON u.id = l.assigned_user_id
                 WHERE {$where}";

        return [
            'total' => (int) $this->db->scalar("SELECT COUNT(*) {$from}", $params),
            'rows' => $this->db->select(
                "SELECT l.id, l.type, l.name, l.email, l.phone, l.message, l.status, l.created_at, l.handled_at,
                        p.reference AS property_reference, p.title AS property_title, a.name AS agency_name,
                        CONCAT(u.first_name, ' ', u.last_name) AS assigned_name
                 {$from}
                 ORDER BY FIELD(l.status, 'new') DESC, l.created_at DESC
                 LIMIT :limit OFFSET :offset",
                $params + ['limit' => $limit, 'offset' => $offset]
            ),
        ];
    }

    /**
     * Compteurs des onglets, filtres courants (hors statut) appliqués.
     *
     * @param array{q?: string, type?: string, periode?: string} $filters
     * @return array<string, int>
     */
    public function countsByStatus(int $countryId, ?int $agencyId, array $filters = []): array
    {
        [$where, $params] = $this->conditions($countryId, $agencyId, ['statut' => ''] + $filters);
        $counts = array_fill_keys(self::STATUSES, 0);
        $sql = "SELECT l.status, COUNT(*) AS n FROM leads l LEFT JOIN properties p ON p.id = l.property_id WHERE {$where} GROUP BY l.status";
        foreach ($this->db->select($sql, $params) as $row) {
            $counts[(string) $row['status']] = (int) $row['n'];
        }

        return $counts;
    }

    /** Pastille du menu : demandes jamais ouvertes. */
    public function newCount(int $countryId, ?int $agencyId): int
    {
        $params = ['country' => $countryId];
        $scope = "l.country_id = :country AND l.status = 'new'";
        if ($agencyId !== null) {
            $scope .= ' AND l.agency_id = :agency';
            $params['agency'] = $agencyId;
        }

        return (int) $this->db->scalar("SELECT COUNT(*) FROM leads l WHERE {$scope}", $params);
    }

    /** @return list<array<string, mixed>> Dernières demandes (encart du tableau de bord) */
    public function latest(int $countryId, ?int $agencyId, int $limit = 6): array
    {
        $params = ['country' => $countryId, 'limit' => $limit];
        $scope = "l.country_id = :country AND l.status <> 'spam'";
        if ($agencyId !== null) {
            $scope .= ' AND l.agency_id = :agency';
            $params['agency'] = $agencyId;
        }

        return $this->db->select(
            "SELECT l.id, l.type, l.name, l.status, l.created_at, p.reference AS property_reference, p.title AS property_title
             FROM leads l LEFT JOIN properties p ON p.id = l.property_id
             WHERE {$scope} ORDER BY l.created_at DESC LIMIT :limit",
            $params
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $countryId, ?int $agencyId = null): ?array
    {
        $params = ['id' => $id, 'country' => $countryId];
        $scope = '';
        if ($agencyId !== null) {
            $scope = ' AND l.agency_id = :agency';
            $params['agency'] = $agencyId;
        }

        return $this->db->selectOne(
            "SELECT l.*, p.reference AS property_reference, p.title AS property_title, p.status AS property_status,
                    p.contact_name AS property_contact_name, p.contact_phone AS property_contact_phone,
                    p.contact_whatsapp AS property_contact_whatsapp, p.contact_email AS property_contact_email,
                    a.name AS agency_name, a.phone AS agency_phone, a.whatsapp AS agency_whatsapp, a.email AS agency_email,
                    CONCAT(u.first_name, ' ', u.last_name) AS assigned_name
             FROM leads l
             LEFT JOIN properties p ON p.id = l.property_id
             LEFT JOIN agencies a ON a.id = l.agency_id
             LEFT JOIN users u ON u.id = l.assigned_user_id
             WHERE l.id = :id AND l.country_id = :country{$scope}",
            $params
        );
    }

    /** Première ouverture : « nouvelle » devient « lue » (sans écraser un suivi déjà engagé). */
    public function markRead(int $id): void
    {
        $this->db->execute("UPDATE leads SET status = 'read' WHERE id = :id AND status = 'new'", ['id' => $id]);
    }

    public function updateStatus(int $id, string $status, ?int $assignedUserId): void
    {
        $this->db->execute(
            'UPDATE leads SET status = :status, assigned_user_id = :assigned,
                    handled_at = CASE WHEN :status2 IN (\'closed\', \'spam\') THEN COALESCE(handled_at, UTC_TIMESTAMP()) ELSE NULL END
             WHERE id = :id',
            ['status' => $status, 'status2' => $status, 'assigned' => $assignedUserId, 'id' => $id]
        );
    }

    /**
     * Conditions communes aux listes (périmètre + filtres).
     *
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function conditions(int $countryId, ?int $agencyId, array $filters): array
    {
        $where = 'l.country_id = :country';
        $params = ['country' => $countryId];

        if ($agencyId !== null) {
            $where .= ' AND l.agency_id = :agency';
            $params['agency'] = $agencyId;
        }
        if (in_array($filters['statut'] ?? '', self::STATUSES, true)) {
            $where .= ' AND l.status = :status';
            $params['status'] = $filters['statut'];
        }
        if (in_array($filters['type'] ?? '', self::TYPES, true)) {
            $where .= ' AND l.type = :type';
            $params['type'] = $filters['type'];
        }
        $days = ['7j' => 7, '30j' => 30, '90j' => 90][$filters['periode'] ?? ''] ?? null;
        if ($days !== null) {
            $where .= ' AND l.created_at >= UTC_TIMESTAMP() - INTERVAL :days DAY';
            $params['days'] = $days;
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where .= ' AND (l.name LIKE :q OR l.email LIKE :q2 OR l.phone LIKE :q3 OR l.message LIKE :q4 OR p.reference LIKE :q5)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $params += ['q' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like, 'q5' => $like];
        }

        return [$where, $params];
    }
}
