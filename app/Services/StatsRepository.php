<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Chiffres et listes du tableau de bord : audience (property_stats_daily), biens les plus consultés,
 * annonces à traiter. Toujours limité au pays, et à l'agence pour un compte agence.
 *
 * L'audience n'est alimentée qu'à partir de la mise en ligne du site public (lots 1.9 et 1.10) :
 * tant qu'aucune ligne n'existe, les écrans affichent un état vide plutôt qu'une courbe à zéro.
 */
final class StatsRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Vues et contacts jour par jour sur les N derniers jours (jours sans donnée compris).
     *
     * @return array{labels: list<string>, views: list<int>, leads: list<int>, totals: array{views: int, leads: int}, has_data: bool}
     */
    public function audience(int $countryId, ?int $agencyId, int $days = 30): array
    {
        [$scope, $params] = $this->propertyScope($countryId, $agencyId);
        $rows = $this->db->select(
            "SELECT s.stat_date, SUM(s.views) AS views, SUM(s.leads) AS leads
             FROM property_stats_daily s JOIN properties p ON p.id = s.property_id
             WHERE {$scope} AND s.stat_date >= UTC_DATE() - INTERVAL :days DAY
             GROUP BY s.stat_date",
            $params + ['days' => $days - 1]
        );

        $byDate = [];
        foreach ($rows as $row) {
            $byDate[(string) $row['stat_date']] = ['views' => (int) $row['views'], 'leads' => (int) $row['leads']];
        }

        $series = ['labels' => [], 'views' => [], 'leads' => [], 'totals' => ['views' => 0, 'leads' => 0], 'has_data' => $rows !== []];
        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $date = gmdate('Y-m-d', strtotime("-{$offset} day", (int) strtotime(gmdate('Y-m-d'))));
            $day = $byDate[$date] ?? ['views' => 0, 'leads' => 0];
            $series['labels'][] = $date;
            $series['views'][] = $day['views'];
            $series['leads'][] = $day['leads'];
            $series['totals']['views'] += $day['views'];
            $series['totals']['leads'] += $day['leads'];
        }

        return $series;
    }

    /**
     * Biens les plus consultés sur la période.
     *
     * @return list<array<string, mixed>>
     */
    public function topProperties(int $countryId, ?int $agencyId, int $days = 30, int $limit = 5): array
    {
        [$scope, $params] = $this->propertyScope($countryId, $agencyId);

        return $this->db->select(
            "SELECT p.id, p.reference, p.title, m.name AS commune_name,
                    SUM(s.views) AS views, SUM(s.leads) AS leads
             FROM property_stats_daily s
             JOIN properties p ON p.id = s.property_id
             LEFT JOIN communes m ON m.id = p.commune_id
             WHERE {$scope} AND s.stat_date >= UTC_DATE() - INTERVAL :days DAY
             GROUP BY p.id, p.reference, p.title, m.name
             HAVING views > 0
             ORDER BY views DESC, leads DESC
             LIMIT :limit",
            $params + ['days' => $days - 1, 'limit' => $limit]
        );
    }

    /**
     * Annonces rejetées (à corriger) ou dont la dernière révision a été refusée, avec le motif.
     *
     * @return list<array<string, mixed>>
     */
    public function toFix(int $countryId, ?int $agencyId, int $limit = 5): array
    {
        [$scope, $params] = $this->propertyScope($countryId, $agencyId);

        return $this->db->select(
            "SELECT p.id, p.reference, p.title, p.status, p.rejection_reason, p.reviewed_at,
                    (SELECT r.rejection_reason FROM property_revisions r
                      WHERE r.property_id = p.id AND r.status = 'rejected' ORDER BY r.id DESC LIMIT 1) AS revision_reason
             FROM properties p
             WHERE {$scope} AND (p.status = 'rejected'
                   OR EXISTS (SELECT 1 FROM property_revisions r2 WHERE r2.property_id = p.id AND r2.status = 'rejected'
                                AND r2.id = (SELECT MAX(r3.id) FROM property_revisions r3 WHERE r3.property_id = p.id)))
             ORDER BY COALESCE(p.reviewed_at, p.updated_at, p.created_at) DESC
             LIMIT :limit",
            $params + ['limit' => $limit]
        );
    }

    /**
     * Annonces publiées qui expirent dans moins de N jours.
     *
     * @return list<array<string, mixed>>
     */
    public function expiringSoon(int $countryId, ?int $agencyId, int $days = 15, int $limit = 5): array
    {
        [$scope, $params] = $this->propertyScope($countryId, $agencyId);

        return $this->db->select(
            "SELECT p.id, p.reference, p.title, p.expires_at,
                    DATEDIFF(p.expires_at, UTC_DATE()) AS days_left
             FROM properties p
             WHERE {$scope} AND p.status = 'published' AND p.expires_at IS NOT NULL
               AND p.expires_at > UTC_TIMESTAMP() AND p.expires_at <= UTC_TIMESTAMP() + INTERVAL :days DAY
             ORDER BY p.expires_at
             LIMIT :limit",
            $params + ['days' => $days, 'limit' => $limit]
        );
    }

    /**
     * Dernières annonces du périmètre, tous statuts confondus.
     *
     * @return list<array<string, mixed>>
     */
    public function recentProperties(int $countryId, ?int $agencyId, int $limit = 5): array
    {
        [$scope, $params] = $this->propertyScope($countryId, $agencyId);

        return $this->db->select(
            "SELECT p.id, p.reference, p.title, p.status, p.price, p.currency_code, p.price_period, p.views_count, p.leads_count,
                    p.created_at, p.updated_at, c.name AS category_name, m.name AS commune_name,
                    (SELECT i.path FROM property_images i WHERE i.property_id = p.id AND i.revision_id IS NULL ORDER BY i.sort_order, i.id LIMIT 1) AS cover_path,
                    (SELECT r.id FROM property_revisions r WHERE r.property_id = p.id AND r.status = 'pending' LIMIT 1) AS pending_revision_id
             FROM properties p
             JOIN property_categories c ON c.id = p.category_id
             LEFT JOIN communes m ON m.id = p.commune_id
             WHERE {$scope}
             ORDER BY COALESCE(p.updated_at, p.created_at) DESC
             LIMIT :limit",
            $params + ['limit' => $limit]
        );
    }

    /** Vues et contacts cumulés des annonces du périmètre depuis toujours (compteurs de properties). */
    public function totals(int $countryId, ?int $agencyId): array
    {
        [$scope, $params] = $this->propertyScope($countryId, $agencyId);
        $row = $this->db->selectOne(
            "SELECT COALESCE(SUM(p.views_count), 0) AS views, COALESCE(SUM(p.leads_count), 0) AS leads FROM properties p WHERE {$scope}",
            $params
        );

        return ['views' => (int) ($row['views'] ?? 0), 'leads' => (int) ($row['leads'] ?? 0)];
    }

    /**
     * Périmètre commun : pays du site, annonces vivantes, et agence pour un compte agence.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function propertyScope(int $countryId, ?int $agencyId): array
    {
        $scope = 'p.country_id = :country AND p.deleted_at IS NULL';
        $params = ['country' => $countryId];
        if ($agencyId !== null) {
            $scope .= ' AND p.agency_id = :agency';
            $params['agency'] = $agencyId;
        }

        return [$scope, $params];
    }
}
