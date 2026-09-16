<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;

/**
 * Tableau de bord du back-office.
 *
 * Espace agence (lot 1.7) : chiffres réels de l'agence connectée — annonces par statut, ce qui l'attend
 * (rejets à corriger, annonces qui expirent), audience et dernières demandes de contact.
 * Équipe interne (lot 1.12) : mêmes chiffres à l'échelle du pays, plus ce qui attend l'équipe
 * (annonces à valider, demandes de partenariat) et le classement des agences.
 *
 * L'audience vient de `property_stats_daily`, alimentée par le site public : tant qu'aucune ligne
 * n'existe, les écrans affichent un état vide plutôt qu'une courbe à zéro.
 */
final class DashboardController extends Controller
{
    private const AUDIENCE_DAYS = 30;
    private const EXPIRY_DAYS = 15;

    public function index(Request $request): Response
    {
        $user = $this->user($request);

        return $user->isAgency() ? $this->agency($request, $user) : $this->staff($request, $user);
    }

    /** Tableau de bord de l'équipe interne : tout le pays du site courant. */
    private function staff(Request $request, User $user): Response
    {
        $site = site() ?? throw new HttpException(403);
        $countryId = $site->country->id;
        if (!$user->canAccessCountry($countryId)) {
            throw new HttpException(403);
        }

        $stats = $this->app->stats();
        $counts = $this->app->properties()->countsByStatus($countryId, null);
        $agencies = $this->app->agencies()->countsByStatus($countryId);
        $requests = $this->app->partnerRequests()->countsByStatus($countryId);
        $newLeads = $this->app->leads()->newCount($countryId, null);
        $toReview = $counts['pending'] + $counts['revision'];
        $audience = $stats->audience($countryId, null, self::AUDIENCE_DAYS);

        $kpis = [
            [
                'label' => __('dashboard.kpi.published'),
                'value' => $counts['published'],
                'hint' => __('dashboard.kpi.published_country'),
                'link' => ['label' => __('dashboard.see_listings'), 'url' => 'annonces?statut=published'],
            ],
            [
                'label' => __('dashboard.kpi.to_review'),
                'value' => $toReview,
                'hint' => $counts['revision'] > 0
                    ? __('dashboard.kpi.pending_revisions', ['count' => $counts['revision']])
                    : __('dashboard.kpi.to_review_hint'),
                'tone' => $toReview > 0 ? 'alert' : null,
                'link' => $toReview > 0 ? ['label' => __('dashboard.review_now'), 'url' => 'annonces?statut=en-attente'] : null,
            ],
            [
                'label' => __('dashboard.kpi.agencies'),
                'value' => $agencies['active'] ?? 0,
                'hint' => __('dashboard.kpi.agencies_hint'),
                'link' => ['label' => __('dashboard.see_agencies'), 'url' => 'agences'],
            ],
            [
                'label' => __('dashboard.kpi.leads'),
                'value' => $newLeads,
                'hint' => __('dashboard.kpi.leads_hint'),
                'tone' => $newLeads > 0 ? 'alert' : null,
                'link' => ['label' => __('dashboard.see_leads'), 'url' => 'contacts'],
            ],
        ];

        return $this->render($request, 'dashboard/staff', [
            'counts' => $counts,
            'kpis' => array_map(static fn (array $kpi): array => array_filter($kpi, static fn (mixed $v): bool => $v !== null), $kpis),
            'audience' => $audience,
            'audienceDays' => self::AUDIENCE_DAYS,
            'expiryDays' => self::EXPIRY_DAYS,
            'top' => $stats->topProperties($countryId, null, self::AUDIENCE_DAYS),
            'topAgencies' => $stats->topAgencies($countryId, self::AUDIENCE_DAYS),
            'toReview' => $stats->toReview($countryId),
            'expiring' => $stats->expiringSoon($countryId, null, self::EXPIRY_DAYS),
            'recent' => $stats->recentProperties($countryId, null),
            'leads' => $this->app->leads()->latest($countryId, null, 5),
            'newRequests' => $requests['new'] ?? 0,
            'country' => $site->country->localizedName(locale()),
            'commission' => $this->commission(),
        ], [
            'title' => __('dashboard.title'),
            'activeMenu' => 'dashboard',
            'plugins' => $audience['has_data'] ? ['chart'] : [],
            'pageScripts' => $audience['has_data'] ? ['js/dashboard.js'] : [],
        ]);
    }

    /**
     * Rappel du paramétrage de la commission, tant qu'il n'est pas décidé (écran Paramètres).
     *
     * @return array{mode: ?string, label: ?string}
     */
    private function commission(): array
    {
        $mode = settings('commission.mode');
        if ($mode === null || $mode === '') {
            return ['mode' => null, 'label' => null];
        }

        $label = match ($mode) {
            'percent' => __('settings.commission.percent_value', ['rate' => format_decimal((float) settings('commission.rate_percent', 0))]),
            'fixed' => __('settings.commission.fixed_value', ['amount' => format_price(settings('commission.fixed_amount'))]),
            default => __('settings.commission.modes.' . $mode),
        };

        return ['mode' => (string) $mode, 'label' => $label];
    }

    private function agency(Request $request, User $user): Response
    {
        $site = site() ?? throw new HttpException(403);
        $countryId = $site->country->id;
        $agencyId = (int) $user->agencyId;
        if (!$user->canAccessCountry($countryId)) {
            throw new HttpException(403);
        }

        $agency = $this->app->agencies()->find($agencyId, $countryId) ?? throw new HttpException(403);
        $stats = $this->app->stats();
        $counts = $this->app->properties()->countsByStatus($countryId, $agencyId);
        $newLeads = $this->app->leads()->newCount($countryId, $agencyId);
        $pending = $counts['pending'] + $counts['revision'];
        $toFix = $stats->toFix($countryId, $agencyId);
        $expiring = $stats->expiringSoon($countryId, $agencyId, self::EXPIRY_DAYS);
        $audience = $stats->audience($countryId, $agencyId, self::AUDIENCE_DAYS);

        $kpis = [
            [
                'label' => __('dashboard.kpi.published'),
                'value' => $counts['published'],
                'hint' => __('dashboard.kpi.published_hint'),
                'link' => ['label' => __('dashboard.see_listings'), 'url' => 'annonces?statut=published'],
            ],
            [
                'label' => __('dashboard.kpi.pending'),
                'value' => $pending,
                'hint' => $counts['revision'] > 0 ? __('dashboard.kpi.pending_revisions', ['count' => $counts['revision']]) : __('dashboard.kpi.pending_hint'),
                'link' => $pending > 0 ? ['label' => __('dashboard.see_listings'), 'url' => 'annonces?statut=pending'] : null,
            ],
            [
                'label' => __('dashboard.kpi.to_fix'),
                'value' => $counts['rejected'],
                'hint' => __('dashboard.kpi.to_fix_hint'),
                'tone' => $counts['rejected'] > 0 ? 'alert' : null,
                'link' => $counts['rejected'] > 0 ? ['label' => __('dashboard.see_listings'), 'url' => 'annonces?statut=rejected'] : null,
            ],
            [
                'label' => __('dashboard.kpi.leads'),
                'value' => $newLeads,
                'hint' => __('dashboard.kpi.leads_hint'),
                'tone' => $newLeads > 0 ? 'alert' : null,
                'link' => ['label' => __('dashboard.see_leads'), 'url' => 'contacts'],
            ],
        ];

        return $this->render($request, 'dashboard/agency', [
            'agency' => $agency,
            'counts' => $counts,
            'kpis' => array_map(static fn (array $kpi): array => array_filter($kpi, static fn (mixed $v): bool => $v !== null), $kpis),
            'audience' => $audience,
            'audienceDays' => self::AUDIENCE_DAYS,
            'expiryDays' => self::EXPIRY_DAYS,
            'top' => $stats->topProperties($countryId, $agencyId, self::AUDIENCE_DAYS),
            'toFix' => $toFix,
            'expiring' => $expiring,
            'recent' => $stats->recentProperties($countryId, $agencyId),
            'leads' => $this->app->leads()->latest($countryId, $agencyId, 5),
            'missing' => $this->profileGaps($agency, $agencyId),
            'isOwner' => $user->role === User::AGENCY_OWNER,
        ], [
            'title' => __('dashboard.title'),
            'activeMenu' => 'dashboard',
            'plugins' => $audience['has_data'] ? ['chart'] : [],
            'pageScripts' => $audience['has_data'] ? ['js/dashboard.js'] : [],
        ]);
    }

    /**
     * Éléments manquants du profil public de l'agence (incitation à le compléter).
     *
     * @param array<string, mixed> $agency
     * @return list<string> Libellés traduits
     */
    private function profileGaps(array $agency, int $agencyId): array
    {
        $missing = [];
        if ($agency['logo_path'] === null) {
            $missing[] = __('agencies.logo');
        }
        if (trim((string) ($agency['description'] ?? '')) === '') {
            $missing[] = __('agencies.description');
        }
        if ($agency['phone'] === null && $agency['email'] === null) {
            $missing[] = __('agencies.contact');
        }
        if ($this->app->agencies()->zoneIds($agencyId) === []) {
            $missing[] = __('agencies.zones');
        }

        return $missing;
    }
}
