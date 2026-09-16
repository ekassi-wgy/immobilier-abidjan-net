<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;

/**
 * Exports CSV des annonces et des demandes de contact (lot 1.12). Rôle `staff`, toujours limité
 * au pays du site courant.
 *
 * Le fichier est produit pour Excel en français : BOM UTF-8, séparateur « ; », fins de ligne CRLF.
 * Les données confidentielles ne sortent jamais : `property_private_details` (notaire, référence de
 * dossier) n'est pas joint, et l'adresse exacte d'une annonce n'est exportée que si elle est publique.
 *
 * Volume : l'export est plafonné (MAX_ROWS) et le fichier est construit en mémoire — suffisant pour
 * le MVP ; au-delà, il faudra passer à un envoi en flux.
 */
final class ExportController extends Controller
{
    private const MAX_ROWS = 10000;

    /** Périodes proposées, en jours (0 = tout l'historique). */
    private const PERIODS = [30, 90, 365, 0];

    public function index(Request $request): Response
    {
        $this->staffOnly($request);

        return $this->render($request, 'exports/index', [
            'periods' => self::PERIODS,
            'statuses' => ['', 'published', 'pending', 'rejected', 'unpublished', 'archived', 'expired'],
            'leadStatuses' => ['', 'new', 'read', 'in_progress', 'closed', 'spam'],
            'maxRows' => self::MAX_ROWS,
        ], [
            'title' => __('exports.title'),
            'activeMenu' => 'exports',
        ]);
    }

    /** Export des annonces du pays, filtré par période et par statut. */
    public function properties(Request $request): Response
    {
        $countryId = $this->staffOnly($request);
        [$since, $days] = $this->period($request);

        $params = ['country' => $countryId];
        $where = 'p.country_id = :country AND p.deleted_at IS NULL';
        if ($since !== null) {
            $where .= ' AND p.created_at >= :since';
            $params['since'] = $since;
        }
        $status = (string) $request->query('statut', '');
        if ($status !== '' && in_array($status, ['published', 'pending', 'rejected', 'unpublished', 'archived', 'expired'], true)) {
            $where .= ' AND p.status = :status';
            $params['status'] = $status;
        }

        $rows = $this->app->db()->select(
            "SELECT p.reference, p.title, p.status, t.name AS transaction, c.name AS category,
                    p.price, p.currency_code, p.price_period, p.is_negotiable, p.charges, p.agency_fee_percent,
                    ci.name AS city, m.name AS commune, d.name AS district,
                    IF(p.show_exact_location = 1, p.address, NULL) AS address,
                    p.living_area, p.land_area, p.rooms, p.bedrooms, p.bathrooms,
                    p.availability, a.name AS agency, p.source,
                    p.views_count, p.leads_count,
                    p.published_at, p.expires_at, p.created_at
             FROM properties p
             JOIN transaction_types t ON t.id = p.transaction_type_id
             JOIN property_categories c ON c.id = p.category_id
             JOIN cities ci ON ci.id = p.city_id
             LEFT JOIN communes m ON m.id = p.commune_id
             LEFT JOIN districts d ON d.id = p.district_id
             LEFT JOIN agencies a ON a.id = p.agency_id
             WHERE {$where}
             ORDER BY p.created_at DESC
             LIMIT :limit",
            $params + ['limit' => self::MAX_ROWS]
        );

        // Les énumérations sortent en clair : un fichier destiné au client ne contient pas de codes.
        foreach ($rows as $index => $row) {
            $rows[$index]['status'] = __('properties.status.' . $row['status']);
            $rows[$index]['availability'] = __('properties.availability.' . $row['availability']);
            $rows[$index]['source'] = __('properties.source.' . $row['source']);
            $rows[$index]['is_negotiable'] = __((int) $row['is_negotiable'] === 1 ? 'common.yes' : 'common.no');
            $rows[$index]['price_period'] = $row['price_period'] === 'total'
                ? ''
                : trim(price_period_label((string) $row['price_period']), '/ ');
        }

        $headers = [];
        foreach ([
            'reference', 'title', 'status', 'transaction', 'category', 'price', 'currency_code', 'price_period',
            'is_negotiable', 'charges', 'agency_fee_percent', 'city', 'commune', 'district', 'address',
            'living_area', 'land_area', 'rooms', 'bedrooms', 'bathrooms', 'availability', 'agency', 'source',
            'views_count', 'leads_count', 'published_at', 'expires_at', 'created_at',
        ] as $column) {
            $headers[$column] = __('exports.columns.' . $column);
        }

        $this->log($request, 'export.properties', 'export', null, __('exports.log_properties', ['count' => count($rows), 'days' => $days]));

        return $this->csv('annonces', $headers, $rows);
    }

    /** Export des demandes de contact du pays, filtré par période et par statut. */
    public function leads(Request $request): Response
    {
        $countryId = $this->staffOnly($request);
        [$since, $days] = $this->period($request);

        $params = ['country' => $countryId];
        $where = 'l.country_id = :country';
        if ($since !== null) {
            $where .= ' AND l.created_at >= :since';
            $params['since'] = $since;
        }
        $status = (string) $request->query('statut', '');
        if ($status !== '' && in_array($status, ['new', 'read', 'in_progress', 'closed', 'spam'], true)) {
            $where .= ' AND l.status = :status';
            $params['status'] = $status;
        }

        $rows = $this->app->db()->select(
            "SELECT l.id, l.created_at, l.type, l.status, l.name, l.email, l.phone, l.message,
                    p.reference AS property_reference, p.title AS property_title,
                    a.name AS agency, CONCAT(u.first_name, ' ', u.last_name) AS assigned_to,
                    l.payload, l.source_url, l.handled_at
             FROM leads l
             LEFT JOIN properties p ON p.id = l.property_id
             LEFT JOIN agencies a ON a.id = l.agency_id
             LEFT JOIN users u ON u.id = l.assigned_user_id
             WHERE {$where}
             ORDER BY l.created_at DESC
             LIMIT :limit",
            $params + ['limit' => self::MAX_ROWS]
        );

        // La charge utile (objet, type de bien proposé…) est aplatie en une colonne lisible.
        foreach ($rows as $index => $row) {
            $payload = $row['payload'] !== null ? json_decode((string) $row['payload'], true) : null;
            $rows[$index]['type'] = __('leads.type.' . $row['type']);
            $rows[$index]['status'] = __('leads.status.' . $row['status']);
            $rows[$index]['payload'] = is_array($payload) ? $this->flatten($payload) : null;
        }

        $headers = [];
        foreach ([
            'id', 'created_at', 'type', 'status', 'name', 'email', 'phone', 'message',
            'property_reference', 'property_title', 'agency', 'assigned_to', 'payload', 'source_url', 'handled_at',
        ] as $column) {
            $headers[$column] = __('exports.columns.' . $column);
        }

        $this->log($request, 'export.leads', 'export', null, __('exports.log_leads', ['count' => count($rows), 'days' => $days]));

        return $this->csv('contacts', $headers, $rows);
    }

    /** Rôle `staff` et pays du site : un compte agence n'exporte rien. */
    private function staffOnly(Request $request): int
    {
        $user = $this->user($request);
        $site = site() ?? throw new HttpException(403);
        if (!$user->isStaff() || !$user->canAccessCountry($site->country->id)) {
            throw new HttpException(403);
        }

        return $site->country->id;
    }

    /**
     * Période demandée : date de début (UTC) et nombre de jours (0 = tout l'historique).
     *
     * @return array{0: ?string, 1: int}
     */
    private function period(Request $request): array
    {
        $days = (int) $request->query('periode', 90);
        if (!in_array($days, self::PERIODS, true)) {
            $days = 90;
        }

        return [$days > 0 ? gmdate('Y-m-d H:i:s', time() - $days * 86400) : null, $days];
    }

    /**
     * Fichier CSV lisible par Excel en français : BOM UTF-8, séparateur « ; », fins de ligne CRLF.
     *
     * @param array<string, string>            $headers [colonne => en-tête]
     * @param list<array<string, mixed>>       $rows
     */
    private function csv(string $name, array $headers, array $rows): Response
    {
        $lines = [$this->line(array_values($headers))];
        foreach ($rows as $row) {
            $line = [];
            foreach (array_keys($headers) as $column) {
                $line[] = $row[$column] ?? '';
            }
            $lines[] = $this->line($line);
        }

        $filename = $name . '-' . gmdate('Y-m-d') . '.csv';

        return new Response("\u{FEFF}" . implode("\r\n", $lines) . "\r\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Une ligne CSV. Une valeur qui commence par =, +, - ou @ est préfixée d'une apostrophe :
     * un tableur ne doit jamais interpréter une donnée saisie par un visiteur comme une formule.
     *
     * @param list<mixed> $values
     */
    private function line(array $values): string
    {
        return implode(';', array_map(static function (mixed $value): string {
            $text = str_replace(["\r\n", "\r", "\n"], ' ', (string) ($value ?? ''));
            if ($text !== '' && str_contains('=+-@', $text[0])) {
                $text = "'" . $text;
            }

            return '"' . str_replace('"', '""', $text) . '"';
        }, $values));
    }

    /** @param array<string, mixed> $payload */
    private function flatten(array $payload): string
    {
        $parts = [];
        foreach ($payload as $key => $value) {
            $label = 'leads.payload.' . $key;
            $parts[] = ($this->app->translator()->has($label) ? __($label) : (string) $key)
                . ' : ' . (is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE));
        }

        return implode(' · ', $parts);
    }
}
