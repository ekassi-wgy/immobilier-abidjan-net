<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin\Properties;

use App\Controllers\Cmsadmin\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\PropertyRepository;
use App\Support\Validator;
use RuntimeException;

/**
 * Décisions sur une annonce : validation, rejet, dépublication, remise en ligne, prolongation,
 * archivage, mise en avant, suppression. Chaque action vérifie le rôle et le statut de départ.
 */
final class PropertyActionController extends Controller
{
    use PropertySupport;

    public function approve(Request $request, string $reference): Response
    {
        $this->requireStaff($request);
        $property = $this->findProperty($request, $reference);
        $revision = $this->app->properties()->pendingRevision((int) $property['id']);

        return $this->run($reference, function () use ($request, $property, $revision): string {
            $user = $this->user($request);
            $site = $this->site();

            if ($revision !== null) {
                $schema = $this->app->catalog()->formSchema((int) ($revision['data']['fields']['category_id'] ?? $property['category_id']), $site->country->id);
                $this->app->workflow()->approveRevision($request, $user, $site, $property, $revision, $schema['attributes'] ?? []);

                return __('properties.flash.revision_approved', ['ref' => $property['reference']]);
            }

            $this->app->workflow()->approve($request, $user, $site, $property);

            return __('properties.flash.approved', ['ref' => $property['reference']]);
        });
    }

    public function reject(Request $request, string $reference): Response
    {
        $this->requireStaff($request);
        $property = $this->findProperty($request, $reference);
        $revision = $this->app->properties()->pendingRevision((int) $property['id']);

        $v = new Validator($request->all());
        $v->required('reason')->maxLength('reason', 2000);
        if ($v->fails()) {
            $this->flash('error', __('properties.errors.reason_required'));

            return $this->redirectToRoute('cmsadmin.properties.show', ['reference' => $reference], 303);
        }

        return $this->run($reference, function () use ($request, $property, $revision, $v): string {
            $user = $this->user($request);
            $site = $this->site();

            if ($revision !== null) {
                $this->app->workflow()->rejectRevision($request, $user, $site, $property, $revision, $v->string('reason'));

                return __('properties.flash.revision_rejected', ['ref' => $property['reference']]);
            }

            $this->app->workflow()->reject($request, $user, $site, $property, $v->string('reason'));

            return __('properties.flash.rejected', ['ref' => $property['reference']]);
        });
    }

    public function unpublish(Request $request, string $reference): Response
    {
        $this->requireStaff($request);
        $property = $this->findProperty($request, $reference);
        $reason = trim((string) $request->input('reason', ''));

        return $this->run($reference, function () use ($request, $property, $reason): string {
            $this->app->workflow()->unpublish($request, $this->user($request), $this->site(), $property, $reason !== '' ? mb_substr($reason, 0, 2000) : null);

            return __('properties.flash.unpublished', ['ref' => $property['reference']]);
        });
    }

    public function republish(Request $request, string $reference): Response
    {
        $this->requireStaff($request);
        $property = $this->findProperty($request, $reference);

        return $this->run($reference, function () use ($request, $property): string {
            $this->app->workflow()->republish($request, $this->user($request), $this->site(), $property);

            return __('properties.flash.republished', ['ref' => $property['reference']]);
        });
    }

    /** Prolongation sans nouvelle validation : le contenu n'a pas changé (agence ou équipe). */
    public function extend(Request $request, string $reference): Response
    {
        $property = $this->findProperty($request, $reference);

        return $this->run($reference, function () use ($request, $property): string {
            $this->app->workflow()->extend($request, $this->user($request), $this->site(), $property);

            return __('properties.flash.extended', ['ref' => $property['reference'], 'days' => (int) settings('listing.lifetime_days', 90)]);
        });
    }

    public function archive(Request $request, string $reference): Response
    {
        $property = $this->findProperty($request, $reference);
        $availability = (string) $request->input('availability', 'sold');
        if (!in_array($availability, ['sold', 'rented'], true)) {
            $availability = 'sold';
        }

        return $this->run($reference, function () use ($request, $property, $availability): string {
            $this->app->workflow()->archive($request, $this->user($request), $this->site(), $property, $availability);

            return __('properties.flash.archived', ['ref' => $property['reference']]);
        });
    }

    public function feature(Request $request, string $reference): Response
    {
        $this->requireStaff($request);
        $property = $this->findProperty($request, $reference);
        $featured = (int) $property['is_featured'] === 0;
        if ($featured && $property['status'] !== 'published') {
            $this->flash('error', __('properties.errors.feature_published_only'));

            return $this->redirectToRoute('cmsadmin.properties.show', ['reference' => $reference], 303);
        }

        $until = trim((string) $request->input('featured_until', ''));
        $until = preg_match('/^\d{4}-\d{2}-\d{2}$/', $until) === 1 ? $until . ' 23:59:59' : null;

        return $this->run($reference, function () use ($request, $property, $featured, $until): string {
            $this->app->workflow()->setFeatured($request, $this->user($request), $this->site(), $property, $featured, $until);

            return __($featured ? 'properties.flash.featured' : 'properties.flash.unfeatured', ['ref' => $property['reference']]);
        });
    }

    public function destroy(Request $request, string $reference): Response
    {
        $user = $this->user($request);
        $property = $this->findProperty($request, $reference);

        // Une agence ne supprime que ses annonces jamais publiées ; sinon elle les archive
        if ($user->isAgency() && !in_array($property['status'], ['pending', 'rejected'], true)) {
            $this->flash('error', __('properties.errors.delete_published'));

            return $this->redirectToRoute('cmsadmin.properties.show', ['reference' => $reference], 303);
        }

        $this->app->workflow()->delete($request, $user, $this->site(), $property);
        $this->flash('success', __('properties.flash.deleted', ['ref' => $reference]));

        return $this->redirectToRoute('cmsadmin.properties.index', status: 303);
    }

    /** Disponibilité (réservé, disponible) sans changer le statut de publication. */
    public function availability(Request $request, string $reference): Response
    {
        $property = $this->findProperty($request, $reference);
        $availability = (string) $request->input('availability', '');
        if (!in_array($availability, PropertyRepository::AVAILABILITIES, true)) {
            throw new HttpException(422);
        }

        $this->app->properties()->update((int) $property['id'], ['availability' => $availability]);
        $this->log($request, 'property.availability', 'property', (int) $property['id'], $property['reference'] . ' → ' . $availability);
        $this->flash('success', __('properties.flash.availability', ['label' => __('properties.availability.' . $availability)]));

        return $this->redirectToRoute('cmsadmin.properties.show', ['reference' => $reference], 303);
    }

    /** Exécute une transition et traduit un refus métier en message d'erreur. */
    private function run(string $reference, callable $action): Response
    {
        try {
            $this->flash('success', (string) $action());
        } catch (RuntimeException $exception) {
            $this->app->logger()->exception($exception, ['reference' => $reference]);
            $this->flash('error', __('properties.errors.transition'));
        }

        return $this->redirectToRoute('cmsadmin.properties.show', ['reference' => $reference], 303);
    }
}
