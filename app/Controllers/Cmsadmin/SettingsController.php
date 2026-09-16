<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Support\Validator;

/**
 * Paramètres de la plateforme (lot 1.12). Réservé au Super Admin.
 *
 * Les valeurs vivent dans `settings` (JSON, `site_id` NULL = global). Une valeur laissée vide est
 * écrite `null`, ce qui signifie « décision en attente » : le code retombe alors sur sa valeur par
 * défaut. C'est le cas de la commission tant que le client ne l'a pas tranchée.
 *
 * Les coordonnées publiques du site ne sont PAS ici : elles vivent sur la table `sites` et se
 * modifient dans « Pays & sites » (une seule source de vérité).
 *
 * Toute écriture vide le cache des sites, qui embarque les paramètres.
 */
final class SettingsController extends Controller
{
    private const COMMISSION_MODES = ['percent', 'fixed', 'premium_listing'];

    /**
     * Champs exposés : clé de paramètre => type de saisie et bornes.
     * Seules ces clés peuvent être écrites depuis l'écran.
     */
    private const FIELDS = [
        'commission.mode' => ['type' => 'enum', 'group' => 'commission'],
        'commission.rate_percent' => ['type' => 'decimal', 'min' => 0, 'max' => 100, 'group' => 'commission'],
        'commission.fixed_amount' => ['type' => 'decimal', 'min' => 0, 'max' => 1000000000, 'group' => 'commission'],
        'listing.lifetime_days' => ['type' => 'int', 'min' => 1, 'max' => 3650, 'group' => 'listing'],
        'listing.expiry_reminder_days' => ['type' => 'int', 'min' => 0, 'max' => 365, 'group' => 'listing'],
        'listing.max_photos' => ['type' => 'int', 'min' => 1, 'max' => 100, 'group' => 'listing'],
        'listing.max_photo_size_mb' => ['type' => 'int', 'min' => 1, 'max' => 50, 'group' => 'listing'],
        'home.featured_limit' => ['type' => 'int', 'min' => 1, 'max' => 24, 'group' => 'listing'],
        'workflow.auto_publish_super_admin' => ['type' => 'bool', 'group' => 'workflow'],
        'workflow.auto_publish_country_admin' => ['type' => 'bool', 'group' => 'workflow'],
        'security.login_max_attempts' => ['type' => 'int', 'min' => 3, 'max' => 20, 'group' => 'security'],
        'security.login_lockout_minutes' => ['type' => 'int', 'min' => 1, 'max' => 1440, 'group' => 'security'],
    ];

    public function edit(Request $request): Response
    {
        $this->superAdminOnly($request);

        return $this->form($request, $this->current());
    }

    public function update(Request $request): Response
    {
        $this->superAdminOnly($request);

        $before = $this->current();
        $validator = new Validator($request->all());
        $values = [];

        foreach (self::FIELDS as $key => $field) {
            $name = $this->fieldName($key);
            $raw = trim((string) $request->input($name, ''));

            $values[$key] = match ($field['type']) {
                'bool' => $request->input($name) === '1',
                'enum' => in_array($raw, self::COMMISSION_MODES, true) ? $raw : null,
                'int' => $this->number($validator, $name, $raw, $field, true),
                default => $this->number($validator, $name, $raw, $field, false),
            };
        }

        // Le mode « pourcentage » sans taux (ou « fixe » sans montant) n'a pas de sens.
        if ($values['commission.mode'] === 'percent' && $values['commission.rate_percent'] === null) {
            $validator->add($this->fieldName('commission.rate_percent'), __('settings.commission.rate_required'));
        }
        if ($values['commission.mode'] === 'fixed' && $values['commission.fixed_amount'] === null) {
            $validator->add($this->fieldName('commission.fixed_amount'), __('settings.commission.amount_required'));
        }

        if ($validator->fails()) {
            return $this->form($request, $values, $validator->errors(), 422);
        }

        $this->app->db()->transaction(function () use ($values): void {
            foreach ($values as $key => $value) {
                $this->app->db()->execute(
                    'INSERT INTO settings (site_id, setting_key, value) VALUES (NULL, :key, :value)
                     ON DUPLICATE KEY UPDATE value = VALUES(value)',
                    ['key' => $key, 'value' => json_encode($value, JSON_UNESCAPED_UNICODE)]
                );
            }
        });

        // Les paramètres sont embarqués dans l'instantané des sites.
        $this->app->sites()->flush();

        $this->log($request, 'settings.updated', 'settings', null, __('settings.title'), $this->diff($before, $values));
        $this->flash('success', __('settings.saved'));

        return $this->redirectToRoute('cmsadmin.settings');
    }

    /**
     * Valeurs actuelles des champs exposés (null = décision en attente).
     *
     * @return array<string, mixed>
     */
    private function current(): array
    {
        $values = [];
        foreach (array_keys(self::FIELDS) as $key) {
            $values[$key] = settings($key);
        }

        return $values;
    }

    /**
     * @param array<string, mixed>  $values
     * @param array<string, string> $errors
     */
    private function form(Request $request, array $values, array $errors = [], int $status = 200): Response
    {
        $groups = [];
        foreach (self::FIELDS as $key => $field) {
            $groups[$field['group']][$key] = $field + [
                'name' => $this->fieldName($key),
                'value' => $values[$key] ?? null,
                'label' => __('settings.fields.' . str_replace('.', '_', $key)),
                'help' => __('settings.help.' . str_replace('.', '_', $key)),
            ];
        }

        return $this->render($request, 'settings/index', [
            'groups' => $groups,
            'modes' => self::COMMISSION_MODES,
            'errors' => $errors,
            'currency' => site()?->country->currencySymbol ?? '',
        ], [
            'title' => __('settings.title'),
            'activeMenu' => 'settings',
        ], $status);
    }

    /** `commission.rate_percent` → `commission_rate_percent` (nom de champ HTML). */
    private function fieldName(string $key): string
    {
        return str_replace('.', '_', $key);
    }

    /**
     * Nombre saisi, ou null si le champ est laissé vide (décision en attente).
     *
     * @param array{min?: int|float, max?: int|float} $field
     */
    private function number(Validator $validator, string $name, string $raw, array $field, bool $integer): int|float|null
    {
        if ($raw === '') {
            return null;
        }

        $normalized = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $raw);
        if (!is_numeric($normalized)) {
            $validator->add($name, __($integer ? 'validation.integer' : 'validation.decimal'));

            return null;
        }

        $value = $integer ? (int) $normalized : (float) $normalized;
        if ($value < ($field['min'] ?? 0) || $value > ($field['max'] ?? PHP_INT_MAX)) {
            $validator->add($name, __('validation.between', ['min' => $field['min'] ?? 0, 'max' => $field['max'] ?? 0]));

            return null;
        }

        return $value;
    }

    private function superAdminOnly(Request $request): void
    {
        if (!$this->user($request)->isSuperAdmin()) {
            throw new HttpException(403);
        }
    }
}
