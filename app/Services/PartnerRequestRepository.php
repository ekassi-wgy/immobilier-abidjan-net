<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Demandes « Devenir partenaire » envoyées depuis le site public (formulaire au lot 1.11).
 */
final class PartnerRequestRepository
{
    public const STATUSES = ['new', 'contacted', 'approved', 'rejected'];

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param array{q?: string, statut?: string} $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function paginate(int $countryId, array $filters, int $limit, int $offset): array
    {
        $where = 'r.country_id = :country';
        $params = ['country' => $countryId];
        if (in_array($filters['statut'] ?? '', self::STATUSES, true)) {
            $where .= ' AND r.status = :status';
            $params['status'] = $filters['statut'];
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where .= ' AND (r.agency_name LIKE :q OR r.contact_name LIKE :q2 OR r.email LIKE :q3 OR r.phone LIKE :q4)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $params += ['q' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }

        $from = "FROM partner_requests r LEFT JOIN cities c ON c.id = r.city_id LEFT JOIN communes m ON m.id = r.commune_id WHERE {$where}";

        return [
            'total' => (int) $this->db->scalar("SELECT COUNT(*) {$from}", $params),
            'rows' => $this->db->select(
                "SELECT r.id, r.agency_name, r.contact_name, r.email, r.phone, r.status, r.listings_estimate, r.created_at, r.agency_id,
                        c.name AS city_name, m.name AS commune_name
                 {$from} ORDER BY FIELD(r.status, 'new', 'contacted', 'approved', 'rejected'), r.created_at DESC LIMIT :limit OFFSET :offset",
                $params + ['limit' => $limit, 'offset' => $offset]
            ),
        ];
    }

    /** @return array<string, int> */
    public function countsByStatus(int $countryId): array
    {
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($this->db->select('SELECT status, COUNT(*) AS n FROM partner_requests WHERE country_id = :country GROUP BY status', ['country' => $countryId]) as $row) {
            $counts[(string) $row['status']] = (int) $row['n'];
        }

        return $counts;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $countryId): ?array
    {
        return $this->db->selectOne(
            'SELECT r.*, c.name AS city_name, m.name AS commune_name, a.name AS created_agency_name,
                    CONCAT(u.first_name, \' \', u.last_name) AS handled_by_name
             FROM partner_requests r
             LEFT JOIN cities c ON c.id = r.city_id LEFT JOIN communes m ON m.id = r.commune_id
             LEFT JOIN agencies a ON a.id = r.agency_id LEFT JOIN users u ON u.id = r.handled_by_user_id
             WHERE r.id = :id AND r.country_id = :country',
            ['id' => $id, 'country' => $countryId]
        );
    }

    /**
     * Pièces justificatives d'un dossier (métadonnées ; le fichier reste dans storage/private).
     *
     * @return list<array<string, mixed>>
     */
    public function files(int $requestId): array
    {
        return $this->db->select(
            "SELECT id, kind, path, mime, size, original_name, created_at FROM partner_request_files
             WHERE partner_request_id = :id ORDER BY FIELD(kind, 'rccm', 'identity', 'tax', 'license', 'other'), id",
            ['id' => $requestId]
        );
    }

    /** @return array<string, mixed>|null Pièce d'un dossier donné (jamais une pièce d'un autre dossier) */
    public function file(int $requestId, int $fileId): ?array
    {
        return $this->db->selectOne(
            'SELECT id, kind, path, mime, size, original_name FROM partner_request_files WHERE id = :file AND partner_request_id = :request',
            ['file' => $fileId, 'request' => $requestId]
        );
    }

    public function update(int $id, string $status, ?string $notes, int $userId, ?int $agencyId = null): void
    {
        $this->db->execute(
            'UPDATE partner_requests SET status = :status, internal_notes = :notes, handled_by_user_id = :user, handled_at = UTC_TIMESTAMP(),
                    agency_id = COALESCE(:agency, agency_id)
             WHERE id = :id',
            ['status' => $status, 'notes' => $notes, 'user' => $userId, 'agency' => $agencyId, 'id' => $id]
        );
    }
}
