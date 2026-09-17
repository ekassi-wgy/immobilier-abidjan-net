<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\SubmissionRepository;
use App\Support\Paginator;
use App\Support\Validator;
use Throwable;

/**
 * Biens confiés par des particuliers : étude du dossier, décision, création de l'annonce.
 *
 * Rôle `staff`, pays du site. Le particulier est prévenu par email de l'étude et d'un refus ; la mise
 * en ligne de l'annonce issue du dossier le prévient aussi (PropertyWorkflow).
 */
final class SubmissionController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $countryId = $this->countryId($request);
        $repo = $this->app->submissions();
        $statut = (string) $request->query('statut', '');
        $filters = [
            'statut' => in_array($statut, SubmissionRepository::STATUSES, true) ? $statut : '',
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
        ];

        $requested = Paginator::fromRequest($request, PHP_INT_MAX, self::PER_PAGE);
        $result = $repo->paginate($countryId, $filters, self::PER_PAGE, $requested->offset);
        $paginator = new Paginator($result['total'], self::PER_PAGE, $requested->page);

        return $this->render($request, 'submissions/index', [
            'rows' => $result['rows'],
            'filters' => $filters,
            'counts' => $repo->countsByStatus($countryId),
            'pagination' => $paginator->toArray(),
        ], [
            'title' => __('submissions.title'),
            'activeMenu' => 'submissions',
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        return $this->screen($request, $this->find($request, (int) $id));
    }

    public function update(Request $request, string $id): Response
    {
        $submission = $this->find($request, (int) $id);
        $site = site() ?? throw new HttpException(403);

        $allowed = $submission['property_id'] !== null ? ['in_review'] : ['submitted', 'in_review', 'rejected'];
        if (in_array($submission['status'], ['published', 'withdrawn'], true)) {
            $allowed = [$submission['status']];
        }
        $v = new Validator($request->all());
        $v->required('status')->in('status', $allowed)->maxLength('internal_notes', 5000)->maxLength('rejection_reason', 2000);
        if ($v->string('status') === 'rejected') {
            $v->required('rejection_reason');
        }
        if ($v->fails()) {
            return $this->screen($request, $submission, $v->errors(), 422);
        }

        $status = $v->string('status');
        $data = [
            'status' => $status,
            'internal_notes' => $v->nullableString('internal_notes'),
            'rejection_reason' => $status === 'rejected' ? $v->nullableString('rejection_reason') : null,
        ];
        $this->app->submissions()->update((int) $submission['id'], $data, $this->user($request)->id);
        $this->log($request, 'submission.' . $status, 'submission', (int) $submission['id'], trim($submission['owner_first_name'] . ' ' . $submission['owner_last_name']),
            $this->diff(['status' => $submission['status'], 'internal_notes' => $submission['internal_notes']], ['status' => $status, 'internal_notes' => $data['internal_notes']]));

        // Le particulier est prévenu quand l'étude commence et en cas de refus.
        if ($status !== $submission['status'] && in_array($status, ['in_review', 'rejected'], true)) {
            $owner = $this->app->users()->findById((int) $submission['user_id']);
            if ($owner !== null) {
                $this->app->ownerMessages()->send(
                    $owner,
                    $site,
                    __('submissions.email.' . $status . '_subject', ['site' => $site->name]),
                    __('owner.status.' . $status),
                    $status === 'rejected'
                        ? __('submissions.email.rejected_body', ['reason' => (string) $data['rejection_reason']])
                        : __('owner.status_help.in_review', ['site' => $site->name]),
                    absolute_url('mon-espace/biens/' . $submission['id'])
                );
            }
        }

        $this->flash('success', __('submissions.flash.updated'));

        return $this->redirectToRoute('cmsadmin.submissions.show', ['id' => (int) $submission['id']], 303);
    }

    /** Crée l'annonce brouillon à partir du dossier, puis ouvre son formulaire pour la finaliser. */
    public function convert(Request $request, string $id): Response
    {
        $submission = $this->find($request, (int) $id);
        $site = site() ?? throw new HttpException(403);

        try {
            $property = $this->app->submissionConverter()->convert($submission, $this->user($request), $site);
        } catch (Throwable $exception) {
            $this->app->logger()->exception($exception, ['action' => 'submission.convert', 'submission' => $submission['id']]);
            $this->flash('error', __('submissions.flash.convert_failed'));

            return $this->redirectToRoute('cmsadmin.submissions.show', ['id' => (int) $submission['id']], 303);
        }

        $this->log($request, 'submission.converted', 'submission', (int) $submission['id'], $property['reference']);
        $this->log($request, 'property.created', 'property', $property['id'], $property['reference'] . ' · ' . __('submissions.convert.log', ['id' => $submission['id']]));
        $this->flash('success', __('submissions.flash.converted', ['ref' => $property['reference']]));

        return $this->redirectToRoute('cmsadmin.properties.edit', ['reference' => $property['reference']], 303);
    }

    /** Photo ou document d'un dossier : équipe du pays uniquement, jamais par le seul identifiant du fichier. */
    public function file(Request $request, string $id, string $file): Response
    {
        $submission = $this->find($request, (int) $id);
        $row = $this->app->submissions()->file((int) $submission['id'], (int) $file) ?? throw new HttpException(404);

        return $this->app->privateFiles()->response((string) $row['path'], (string) $row['mime'], (string) $row['original_name'], $request->query('apercu') === '1');
    }

    /**
     * @param array<string, mixed>  $submission
     * @param array<string, string> $errors
     */
    private function screen(Request $request, array $submission, array $errors = [], int $status = 200): Response
    {
        $repo = $this->app->submissions();

        return $this->render($request, 'submissions/show', [
            'item' => $submission,
            'photos' => $repo->files((int) $submission['id'], 'photo'),
            'documents' => $repo->files((int) $submission['id'], 'document'),
            'errors' => $errors,
        ], [
            'title' => __('submissions.show_title', ['id' => $submission['id']]),
            'activeMenu' => 'submissions',
        ], $status);
    }

    /** @return array<string, mixed> */
    private function find(Request $request, int $id): array
    {
        return $this->app->submissions()->find($id, $this->countryId($request)) ?? throw new HttpException(404);
    }

    private function countryId(Request $request): int
    {
        if (!$this->user($request)->isStaff()) {
            throw new HttpException(403);
        }

        return site()?->country->id ?? throw new HttpException(403);
    }
}
