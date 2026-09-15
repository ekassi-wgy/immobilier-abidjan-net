<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\LeadRepository;
use App\Support\Paginator;
use App\Support\Validator;

/**
 * Demandes de contact reçues des visiteurs (lot 1.7).
 *
 * Périmètre : pays du site pour l'équipe interne, agence destinataire pour un compte agence
 * (jamais un identifiant d'agence passé en paramètre). Les formulaires publics qui alimentent
 * cette table arrivent au lot 1.11 : d'ici là les écrans sont vides.
 */
final class LeadController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $countryId = $this->countryId();
        $agencyId = $this->scopeAgency($request);
        $repo = $this->app->leads();

        $statut = (string) $request->query('statut', '');
        $type = (string) $request->query('type', '');
        $periode = (string) $request->query('periode', '');
        $filters = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'statut' => in_array($statut, LeadRepository::STATUSES, true) ? $statut : '',
            'type' => in_array($type, LeadRepository::TYPES, true) ? $type : '',
            'periode' => in_array($periode, ['7j', '30j', '90j'], true) ? $periode : '',
        ];

        $requested = Paginator::fromRequest($request, PHP_INT_MAX, self::PER_PAGE);
        $result = $repo->paginate($countryId, $agencyId, $filters, self::PER_PAGE, $requested->offset);
        $paginator = new Paginator($result['total'], self::PER_PAGE, $requested->page);
        if ($paginator->page !== $requested->page) {
            $result = $repo->paginate($countryId, $agencyId, $filters, self::PER_PAGE, $paginator->offset);
        }

        return $this->render($request, 'leads/index', [
            'rows' => $result['rows'],
            'filters' => $filters,
            'counts' => $repo->countsByStatus($countryId, $agencyId, $filters),
            'types' => $this->typeOptions($agencyId !== null),
            'isAgency' => $agencyId !== null,
            'pagination' => $paginator->toArray(),
        ], [
            'title' => __('leads.title'),
            'activeMenu' => 'leads',
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $lead = $this->find($request, (int) $id);
        $this->app->leads()->markRead((int) $lead['id']);

        return $this->detail($request, $lead);
    }

    public function update(Request $request, string $id): Response
    {
        $lead = $this->find($request, (int) $id);

        $v = new Validator($request->all());
        $v->required('status')->in('status', LeadRepository::STATUSES);
        $assigned = $v->nullableInt('assigned_user_id');
        if ($assigned !== null && !isset($this->assignableUsers($request)[$assigned])) {
            $v->add('assigned_user_id', __('validation.in'));
        }
        if ($v->fails()) {
            return $this->detail($request, $lead, $v->errors(), 422);
        }

        $status = $v->string('status');
        $this->app->leads()->updateStatus((int) $lead['id'], $status, $assigned);
        $this->log($request, 'lead.updated', 'lead', (int) $lead['id'], (string) $lead['name'], $this->diff(
            ['status' => $lead['status'], 'assigned_user_id' => $lead['assigned_user_id']],
            ['status' => $status, 'assigned_user_id' => $assigned]
        ));
        $this->flash('success', __('leads.flash.updated'));

        return $this->backTo($request, '/cmsadmin/contacts');
    }

    /**
     * @param array<string, mixed>  $lead
     * @param array<string, string> $errors
     */
    private function detail(Request $request, array $lead, array $errors = [], int $status = 200): Response
    {
        return $this->render($request, 'leads/show', [
            'lead' => $lead,
            'errors' => $errors,
            'users' => $this->assignableUsers($request),
            'back' => $this->backPath($request),
        ], [
            'title' => $lead['name'],
            'activeMenu' => 'leads',
        ], $status);
    }

    /**
     * Personnes à qui confier la demande : l'équipe du pays, ou les comptes de l'agence.
     *
     * @return array<int, string>
     */
    private function assignableUsers(Request $request): array
    {
        $user = $this->user($request);
        if ($user->isAgency()) {
            $rows = array_filter($this->app->users()->forAgency((int) $user->agencyId), static fn (array $row): bool => (int) $row['is_active'] === 1);
        } else {
            $rows = $this->app->db()->select(
                "SELECT id, first_name, last_name FROM users
                 WHERE is_active = 1 AND deleted_at IS NULL
                   AND (role = 'super_admin' OR (role = 'country_admin' AND country_id = :country))
                 ORDER BY first_name, last_name",
                ['country' => $this->countryId()]
            );
        }

        $options = [];
        foreach ($rows as $row) {
            $options[(int) $row['id']] = trim($row['first_name'] . ' ' . $row['last_name']);
        }

        return $options;
    }

    /** @return array<string, string> */
    private function typeOptions(bool $isAgency): array
    {
        $types = $isAgency ? LeadRepository::AGENCY_TYPES : LeadRepository::TYPES;

        return array_combine($types, array_map(static fn (string $type): string => __('leads.type.' . $type), $types));
    }

    /** @return array<string, mixed> */
    private function find(Request $request, int $id): array
    {
        return $this->app->leads()->find($id, $this->countryId(), $this->scopeAgency($request)) ?? throw new HttpException(404);
    }

    /** Agence du compte connecté (null pour l'équipe interne, qui voit tout le pays). */
    private function scopeAgency(Request $request): ?int
    {
        $user = $this->user($request);

        return $user->isAgency() ? (int) $user->agencyId : null;
    }

    private function countryId(): int
    {
        return site()?->country->id ?? throw new HttpException(403);
    }
}
