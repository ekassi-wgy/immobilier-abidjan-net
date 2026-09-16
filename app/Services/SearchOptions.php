<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Données du module de recherche rapide : transactions, types de biens et paliers de budget.
 * Partagé par l'accueil (lot 1.8) et la page de résultats (lot 1.9).
 *
 * Les paliers sont pensés pour le franc CFA (XOF / XAF) ; ils seront à revoir pour une autre devise.
 */
final class SearchOptions
{
    /** Paliers de budget par période de prix de la transaction. */
    private const STEPS = [
        'total' => [10_000_000, 25_000_000, 50_000_000, 100_000_000, 200_000_000, 400_000_000],
        'month' => [100_000, 250_000, 500_000, 1_000_000, 2_500_000, 5_000_000],
        'week' => [100_000, 250_000, 500_000, 1_000_000],
        'night' => [25_000, 50_000, 100_000, 200_000],
        'year' => [1_000_000, 2_500_000, 5_000_000, 10_000_000, 25_000_000],
    ];

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return array{transactions: list<array{slug: string, label: string}>,
     *               propertyTypes: array<string, array<string, string>>,
     *               budgets: array<string, array<int, string>>,
     *               locationPlaceholder: string}
     */
    public function all(int $countryId): array
    {
        $transactions = $this->transactions();

        $budgets = [];
        foreach ($transactions as $transaction) {
            $budgets[$transaction['slug']] = $this->budgetSteps($transaction['period']);
        }

        return [
            'transactions' => array_map(
                static fn (array $t): array => ['slug' => $t['slug'], 'label' => $t['label']],
                $transactions
            ),
            'propertyTypes' => $this->propertyTypes($countryId),
            'budgets' => $budgets,
            'locationPlaceholder' => __('front.search.location_placeholder'),
        ];
    }

    /**
     * Transactions proposées dans les onglets : vente, location, location meublée d'abord.
     *
     * @return list<array{slug: string, label: string, period: string}>
     */
    public function transactions(): array
    {
        $rows = $this->db->select(
            "SELECT slug, name, name_translations, default_price_period
             FROM transaction_types WHERE is_active = 1 ORDER BY sort_order, name"
        );

        return array_map(static fn (array $row): array => [
            'slug' => (string) $row['slug'],
            'label' => self::localized($row['name'], $row['name_translations']),
            'period' => (string) $row['default_price_period'],
        ], $rows);
    }

    /**
     * Types de biens, groupés par famille (optgroup) : [famille => [slug => libellé]].
     *
     * @return array<string, array<string, string>>
     */
    public function propertyTypes(int $countryId): array
    {
        $rows = $this->db->select(
            'SELECT c.slug, c.name, c.name_translations, f.name AS family_name, f.name_translations AS family_translations,
                    f.sort_order AS family_order, c.sort_order
             FROM property_categories c
             JOIN property_categories f ON f.id = c.parent_id
             WHERE c.is_active = 1 AND f.is_active = 1
               AND (c.country_id IS NULL OR c.country_id = :country)
             ORDER BY f.sort_order, c.sort_order, c.name',
            ['country' => $countryId]
        );

        $groups = [];
        foreach ($rows as $row) {
            $family = self::localized($row['family_name'], $row['family_translations']);
            $groups[$family][(string) $row['slug']] = self::localized($row['name'], $row['name_translations']);
        }

        return $groups;
    }

    /**
     * Paliers « budget maximum » d'une période de prix.
     *
     * @return array<int, string> [montant => libellé]
     */
    public function budgetSteps(string $period): array
    {
        $steps = [];
        foreach (self::STEPS[$period] ?? self::STEPS['total'] as $amount) {
            $steps[$amount] = $this->shortPrice($amount) . price_period_label($period);
        }

        return $steps;
    }

    /** « 25 millions FCFA », « 500 000 FCFA » : montants courts, lisibles dans une liste déroulante. */
    private function shortPrice(int $amount): string
    {
        $currency = site()?->country->currencySymbol ?? 'FCFA';
        if ($amount >= 1_000_000) {
            $millions = $amount / 1_000_000;
            $value = $millions == (int) $millions ? (string) (int) $millions : number_format($millions, 1, ',', '');

            return __($millions < 2 ? 'front.search.million' : 'front.search.millions', ['value' => $value, 'currency' => $currency]);
        }

        return format_number($amount) . "\u{00A0}" . $currency;
    }

    /** Libellé dans la langue du site, avec repli sur le libellé français du référentiel. */
    private static function localized(mixed $name, mixed $translations): string
    {
        $locale = locale();
        if ($locale !== 'fr' && is_string($translations) && $translations !== '') {
            $decoded = json_decode($translations, true);
            if (is_array($decoded) && !empty($decoded[$locale])) {
                return (string) $decoded[$locale];
            }
        }

        return (string) $name;
    }
}
