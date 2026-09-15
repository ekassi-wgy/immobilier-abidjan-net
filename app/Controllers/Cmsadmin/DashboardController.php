<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin;

use App\Controllers\Preview\CmsadminPreviewController;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;

/**
 * Tableau de bord du back-office.
 *
 * Espace agence (lot 1.7) : chiffres réels de l'agence connectée — annonces par statut, ce qui l'attend
 * (rejets à corriger, annonces qui expirent), audience et dernières demandes de contact.
 * Le tableau de bord de l'équipe interne reste la maquette de prévisualisation jusqu'au lot 1.12.
 */
final class DashboardController extends Controller
{
    private const AUDIENCE_DAYS = 30;
    private const EXPIRY_DAYS = 15;

    public function index(Request $request): Response
    {
        $user = $this->user($request);
        if ($user->isAgency()) {
            return $this->agency($request, $user);
        }

        // Équipe interne : maquette locale en attendant le tableau de bord réel (lot 1.12)
        if ((bool) $this->app->config->get('app.preview')) {
            return (new CmsadminPreviewController($this->app))->dashboard($request);
        }

        return $this->redirectToRoute('cmsadmin.account');
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
