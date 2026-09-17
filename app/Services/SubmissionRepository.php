<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Biens confiés à Weblogy par des particuliers (`property_submissions`) et leurs fichiers.
 *
 * Un particulier ne publie jamais : il transmet un dossier, que l'équipe étudie puis convertit en
 * annonce (`property_id`). Toute lecture est limitée au pays du site ; côté particulier, en plus, à
 * son propre compte (un dossier d'un autre compte n'existe pas pour lui).
 */
final class SubmissionRepository
{
    public const STATUSES = ['submitted', 'in_review', 'published', 'rejected', 'withdrawn'];

    private const COLUMNS = "s.*, t.name AS transaction_name, c.name AS category_name, ci.name AS city_name,
        m.name AS commune_name, d.name AS district_name,
        p.reference AS property_reference, p.slug AS property_slug, p.status AS property_status,
        (SELECT f.id FROM property_submission_files f WHERE f.submission_id = s.id AND f.kind = 'photo' ORDER BY f.sort_order, f.id LIMIT 1) AS cover_id,
        (SELECT COUNT(*) FROM property_submission_files f WHERE f.submission_id = s.id AND f.kind = 'photo') AS photos_count,
        (SELECT COUNT(*) FROM property_submission_files f WHERE f.submission_id = s.id AND f.kind = 'document') AS documents_count";

    private const JOINS = "FROM property_submissions s
        JOIN transaction_types t ON t.id = s.transaction_type_id
        JOIN property_categories c ON c.id = s.category_id
        JOIN cities ci ON ci.id = s.city_id
        LEFT JOIN communes m ON m.id = s.commune_id
        LEFT JOIN districts d ON d.id = s.district_id
        LEFT JOIN properties p ON p.id = s.property_id AND p.deleted_at IS NULL";

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * État présenté au particulier : il ne voit ni les statuts internes de l'annonce ni le travail
     * de l'équipe, seulement où en est son bien.
     *
     * @param array<string, mixed> $row Ligne issue de COLUMNS (avec property_status)
     * @return array{code: string, label: string, variant: string}
     */
    public static function ownerStatus(array $row): array
    {
        $code = match (true) {
            $row['status'] === 'withdrawn' => 'withdrawn',
            $row['status'] === 'rejected' => 'rejected',
            ($row['property_status'] ?? null) === 'published' => 'online',
            in_array($row['property_status'] ?? null, ['archived'], true) => 'closed',
            $row['property_id'] !== null => 'preparing',
            $row['status'] === 'in_review' => 'in_review',
            default => 'received',
        };
        $variant = ['online' => 'success', 'rejected' => 'neutral', 'withdrawn' => 'neutral', 'closed' => 'neutral', 'preparing' => 'featured'][$code] ?? 'soft';

        return ['code' => $code, 'label' => __('owner.status.' . $code), 'variant' => $variant];
    }

    // -- Particulier ------------------------------------------------------------------------

    /** @return list<array<string, mixed>> Dossiers d'un particulier, les plus récents d'abord */
    public function forOwner(int $userId, int $countryId): array
    {
        return $this->db->select(
            'SELECT ' . self::COLUMNS . ' ' . self::JOINS . '
             WHERE s.user_id = :user AND s.country_id = :country
             ORDER BY s.created_at DESC',
            ['user' => $userId, 'country' => $countryId]
        );
    }

    /** @return array<string, mixed>|null Dossier du particulier (null pour le dossier d'un autre compte) */
    public function findForOwner(int $id, int $userId, int $countryId): ?array
    {
        return $this->db->selectOne(
            'SELECT ' . self::COLUMNS . ' ' . self::JOINS . '
             WHERE s.id = :id AND s.user_id = :user AND s.country_id = :country',
            ['id' => $id, 'user' => $userId, 'country' => $countryId]
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param list<array{kind: string, path: string, mime: string, size: int, original_name: string}> $files
     */
    public function create(array $data, array $files): int
    {
        return $this->db->transaction(function (Database $db) use ($data, $files): int {
            $id = $db->insert('property_submissions', $data);
            foreach ($files as $order => $file) {
                $db->insert('property_submission_files', $file + ['submission_id' => $id, 'sort_order' => $order]);
            }

            return $id;
        });
    }

    /** Retrait par le particulier, tant que l'équipe n'a pas créé l'annonce. */
    public function withdraw(int $id, int $userId): bool
    {
        return $this->db->execute(
            "UPDATE property_submissions SET status = 'withdrawn' WHERE id = :id AND user_id = :user AND status IN ('submitted', 'in_review') AND property_id IS NULL",
            ['id' => $id, 'user' => $userId]
        ) > 0;
    }

    // -- Équipe Weblogy ---------------------------------------------------------------------

    /**
     * @param array{statut?: string, q?: string} $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function paginate(int $countryId, array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->conditions($countryId, $filters);

        return [
            'total' => (int) $this->db->scalar('SELECT COUNT(*) ' . self::JOINS . " JOIN users u ON u.id = s.user_id WHERE {$where}", $params),
            'rows' => $this->db->select(
                'SELECT ' . self::COLUMNS . ", CONCAT(u.first_name, ' ', u.last_name) AS owner_name, u.email AS owner_email, u.phone AS owner_phone
                 " . self::JOINS . " JOIN users u ON u.id = s.user_id
                 WHERE {$where}
                 ORDER BY FIELD(s.status, 'submitted') DESC, s.created_at DESC
                 LIMIT :limit OFFSET :offset",
                $params + ['limit' => $limit, 'offset' => $offset]
            ),
        ];
    }

    /** @return array<string, int> */
    public function countsByStatus(int $countryId): array
    {
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($this->db->select('SELECT status, COUNT(*) AS n FROM property_submissions WHERE country_id = :country GROUP BY status', ['country' => $countryId]) as $row) {
            $counts[(string) $row['status']] = (int) $row['n'];
        }

        return $counts;
    }

    /** Pastille du menu : dossiers jamais ouverts. */
    public function newCount(int $countryId): int
    {
        return (int) $this->db->scalar("SELECT COUNT(*) FROM property_submissions WHERE country_id = :country AND status = 'submitted'", ['country' => $countryId]);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $countryId): ?array
    {
        return $this->db->selectOne(
            'SELECT ' . self::COLUMNS . ", u.first_name AS owner_first_name, u.last_name AS owner_last_name, u.email AS owner_email,
                    u.phone AS owner_phone, u.email_verified_at AS owner_verified_at, u.created_at AS owner_since,
                    CONCAT(h.first_name, ' ', h.last_name) AS handled_by_name
             " . self::JOINS . '
             JOIN users u ON u.id = s.user_id
             LEFT JOIN users h ON h.id = s.handled_by_user_id
             WHERE s.id = :id AND s.country_id = :country',
            ['id' => $id, 'country' => $countryId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function files(int $submissionId, ?string $kind = null): array
    {
        $params = ['id' => $submissionId];
        $where = 'submission_id = :id';
        if ($kind !== null) {
            $where .= ' AND kind = :kind';
            $params['kind'] = $kind;
        }

        return $this->db->select("SELECT id, kind, path, mime, size, original_name FROM property_submission_files WHERE {$where} ORDER BY kind, sort_order, id", $params);
    }

    /** @return array<string, mixed>|null Fichier d'un dossier donné (jamais celui d'un autre dossier) */
    public function file(int $submissionId, int $fileId): ?array
    {
        return $this->db->selectOne(
            'SELECT id, kind, path, mime, size, original_name FROM property_submission_files WHERE id = :file AND submission_id = :id',
            ['file' => $fileId, 'id' => $submissionId]
        );
    }

    /** @param array<string, mixed> $data status, rejection_reason, internal_notes, property_id */
    public function update(int $id, array $data, int $userId): void
    {
        $data += ['handled_by_user_id' => $userId];
        $sets = implode(', ', array_map(static fn (string $column): string => "`{$column}` = :{$column}", array_keys($data)));
        $this->db->execute("UPDATE property_submissions SET {$sets}, handled_at = UTC_TIMESTAMP() WHERE id = :id", $data + ['id' => $id]);
    }

    /**
     * Annonce issue d'un dossier passée en ligne : le dossier est marqué « publié ».
     *
     * @return list<array<string, mixed>> Dossiers concernés (pour prévenir leur propriétaire)
     */
    public function markPublished(int $propertyId): array
    {
        $rows = $this->db->select(
            "SELECT id, user_id, site_id FROM property_submissions WHERE property_id = :property AND status IN ('submitted', 'in_review')",
            ['property' => $propertyId]
        );
        if ($rows !== []) {
            $this->db->execute(
                "UPDATE property_submissions SET status = 'published' WHERE property_id = :property AND status IN ('submitted', 'in_review')",
                ['property' => $propertyId]
            );
        }

        return $rows;
    }

    /**
     * @param array{statut?: string, q?: string} $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function conditions(int $countryId, array $filters): array
    {
        $where = 's.country_id = :country';
        $params = ['country' => $countryId];
        if (in_array($filters['statut'] ?? '', self::STATUSES, true)) {
            $where .= ' AND s.status = :status';
            $params['status'] = $filters['statut'];
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where .= ' AND (u.last_name LIKE :q OR u.first_name LIKE :q2 OR u.email LIKE :q3 OR u.phone LIKE :q4)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $params += ['q' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }

        return [$where, $params];
    }
}
