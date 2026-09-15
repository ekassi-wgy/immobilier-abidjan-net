<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin\Agencies;

use App\Controllers\Cmsadmin\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\PartnerRequestRepository;
use App\Support\Paginator;
use App\Support\Validator;

/**
 * Demandes « Devenir partenaire » du pays du site : suivi (nouvelle, contactée, rejetée) et création de l'agence.
 * L'approbation se fait uniquement en créant l'agence à partir de la demande.
 */
final class PartnerRequestController extends Controller
{
    use AccountSupport;

    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $countryId = $this->countryId();
        $repo = $this->app->partnerRequests();
        $statut = (string) $request->query('statut', '');
        $filters = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'statut' => in_array($statut, PartnerRequestRepository::STATUSES, true) ? $statut : '',
        ];

        $requested = Paginator::fromRequest($request, PHP_INT_MAX, self::PER_PAGE);
        $result = $repo->paginate($countryId, $filters, self::PER_PAGE, $requested->offset);
        $paginator = new Paginator($result['total'], self::PER_PAGE, $requested->page);
        if ($paginator->page !== $requested->page) {
            $result = $repo->paginate($countryId, $filters, self::PER_PAGE, $paginator->offset);
        }

        return $this->render($request, 'partner-requests/index', [
            'rows' => $result['rows'],
            'filters' => $filters,
            'counts' => $repo->countsByStatus($countryId),
            'pagination' => $paginator->toArray(),
        ], [
            'title' => __('partners.title'),
            'activeMenu' => 'agencies.requests',
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        return $this->screen($request, $this->find((int) $id));
    }

    public function update(Request $request, string $id): Response
    {
        $partnerRequest = $this->find((int) $id);
        $v = new Validator($request->all());
        // Une demande approuvée (agence créée) ne change plus d'état ; ses notes restent modifiables
        $allowed = $partnerRequest['status'] === 'approved' ? ['approved'] : ['new', 'contacted', 'rejected'];
        $v->required('status')->in('status', $allowed)->maxLength('internal_notes', 5000);
        if ($v->fails()) {
            return $this->screen($request, ['status' => $v->string('status'), 'internal_notes' => $v->string('internal_notes')] + $partnerRequest, $v->errors(), 422);
        }

        $this->app->partnerRequests()->update((int) $partnerRequest['id'], $v->string('status'), $v->nullableString('internal_notes'), $this->user($request)->id);
        $this->log($request, 'partner_request.' . $v->string('status'), 'partner_request', (int) $partnerRequest['id'], (string) $partnerRequest['agency_name'],
            $this->diff(['status' => $partnerRequest['status'], 'internal_notes' => $partnerRequest['internal_notes']], ['status' => $v->string('status'), 'internal_notes' => $v->nullableString('internal_notes')]));
        $this->flash('success', __('partners.flash.updated', ['name' => $partnerRequest['agency_name']]));

        return $this->redirectToRoute('cmsadmin.partners.show', ['id' => (int) $partnerRequest['id']], 303);
    }

    /**
     * @param array<string, mixed>  $partnerRequest
     * @param array<string, string> $errors
     */
    private function screen(Request $request, array $partnerRequest, array $errors = [], int $status = 200): Response
    {
        $existingAccount = $this->app->users()->emailExists((string) $partnerRequest['email']);

        return $this->render($request, 'partner-requests/show', [
            'item' => $partnerRequest,
            'errors' => $errors,
            'emailInUse' => $existingAccount,
        ], [
            'title' => (string) $partnerRequest['agency_name'],
            'activeMenu' => 'agencies.requests',
        ], $status);
    }

    /** @return array<string, mixed> */
    private function find(int $id): array
    {
        return $this->app->partnerRequests()->find($id, $this->countryId()) ?? throw new HttpException(404);
    }
}
