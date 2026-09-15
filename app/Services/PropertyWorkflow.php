<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Models\Site;
use App\Models\User;
use RuntimeException;
use Throwable;

/**
 * Règles métier des annonces (cahier des charges §2.1 + décision client sur les révisions).
 *
 * Création : agence → « en attente » ; Super Admin / Admin Pays → publiée directement si le paramètre
 * workflow.auto_publish_* l'autorise, sinon en attente.
 *
 * Modification :
 *  - équipe : appliquée directement (une révision d'agence en attente est alors remplacée) ;
 *  - agence, annonce en attente : appliquée, reste en attente ;
 *  - agence, annonce rejetée / dépubliée / expirée : appliquée et renvoyée en validation ;
 *  - agence, annonce PUBLIÉE : révision en attente, la version en ligne reste visible jusqu'à validation ;
 *  - annonce archivée : non modifiable par l'agence.
 *
 * Chaque changement de statut est historisé (property_status_history), journalisé et notifié.
 */
final class PropertyWorkflow
{
    public const OUTCOME_CREATED = 'created';
    public const OUTCOME_PUBLISHED = 'published';
    public const OUTCOME_UPDATED = 'updated';
    public const OUTCOME_RESUBMITTED = 'resubmitted';
    public const OUTCOME_REVISION = 'revision';

    public function __construct(
        private readonly Database $db,
        private readonly PropertyRepository $properties,
        private readonly PendingUploads $uploads,
        private readonly ImageUploader $images,
        private readonly Notifier $notifier,
        private readonly ActivityLogger $activity,
        private readonly Logger $logger,
        private readonly Settings $settings,
    ) {
    }

    /**
     * @param array<string, mixed>              $payload PropertyForm::validate()
     * @param array<int, array<string, mixed>>  $schemaAttributes
     * @return array{0: int, 1: string} Identifiant, résultat
     */
    public function create(Request $request, User $user, Site $site, array $payload, array $schemaAttributes): array
    {
        $publishNow = ($user->isSuperAdmin() && (bool) $this->settings->get('workflow.auto_publish_super_admin', true))
            || ($user->isCountryAdmin() && (bool) $this->settings->get('workflow.auto_publish_country_admin', false));
        $now = gmdate('Y-m-d H:i:s');

        $id = $this->db->transaction(function () use ($user, $site, $payload, $schemaAttributes, $publishNow, $now): int {
            $data = $payload['fields'] + [
                'country_id' => $site->country->id,
                'currency_code' => $site->country->currencyCode,
                'status' => $publishNow ? 'published' : 'pending',
                'submitted_at' => $now,
                'created_by_user_id' => $user->id,
                'updated_by_user_id' => $user->id,
            ];
            if ($publishNow) {
                $data += ['published_at' => $now, 'expires_at' => $this->expiryDate(), 'reviewed_by_user_id' => $user->id, 'reviewed_at' => $now];
            }
            if ($payload['featured'] !== null) {
                $data += $payload['featured'];
            }

            $id = $this->properties->create($data, (string) $this->settings->get('listing.reference_prefix', 'IAN'));
            $this->properties->saveAttributeValues($id, $schemaAttributes, $payload['attributes']);
            $this->properties->saveFeatures($id, $payload['features']);
            $this->properties->savePrivateDetails($id, $payload['private']);
            $this->syncImages($id, $site, $payload['images'], null, true);
            $this->storeDocument($id, $site, $payload['document'], null);
            $this->properties->recordStatus($id, null, $data['status'], null, $user->id);

            return $id;
        });

        $property = $this->reload($id, $site);
        $this->activity->log('property.created', $user->id, $site->country->id, 'property', $id, $property['reference'] . ' · ' . $property['title'], request: $request);

        if ($publishNow) {
            $this->activity->log('property.published', $user->id, $site->country->id, 'property', $id, $property['reference'], request: $request);

            return [$id, self::OUTCOME_PUBLISHED];
        }

        $this->notifyStaff($site, $property, 'property.submitted', __('properties.notify.submitted_title', ['ref' => $property['reference']]), __('properties.notify.submitted_body', ['title' => $property['title'], 'agency' => $property['agency_name'] ?? __('properties.source.' . $property['source'])]));

        return [$id, self::OUTCOME_CREATED];
    }

    /**
     * @param array<string, mixed>              $property
     * @param array<string, mixed>              $payload
     * @param array<int, array<string, mixed>>  $schemaAttributes
     */
    public function update(Request $request, User $user, Site $site, array $property, array $payload, array $schemaAttributes): string
    {
        $id = (int) $property['id'];
        $status = (string) $property['status'];
        $pendingRevision = $this->properties->pendingRevision($id);

        if ($user->isAgency() && $status === 'archived') {
            throw new RuntimeException('Annonce archivée : modification refusée.');
        }

        // Agence sur une annonce publiée : révision, la version en ligne ne bouge pas
        if ($user->isAgency() && $status === 'published') {
            $this->storeRevision($user, $site, $property, $payload, $pendingRevision);
            $this->activity->log('property.revision_submitted', $user->id, $site->country->id, 'property', $id, (string) $property['reference'], request: $request);
            $this->notifyStaff($site, $property, 'property.revision_submitted', __('properties.notify.revision_title', ['ref' => $property['reference']]), __('properties.notify.revision_body', ['title' => $payload['fields']['title'], 'agency' => $property['agency_name'] ?? '']));

            return self::OUTCOME_REVISION;
        }

        $resubmit = $user->isAgency() && in_array($status, ['rejected', 'unpublished', 'expired'], true);
        $before = array_intersect_key($property, array_flip(PropertyRepository::EDITABLE_FIELDS));

        $this->db->transaction(function () use ($user, $site, $property, $payload, $schemaAttributes, $resubmit, $pendingRevision, $id, $status): void {
            $data = $payload['fields'] + ['updated_by_user_id' => $user->id];
            if ($resubmit) {
                $data += ['status' => 'pending', 'submitted_at' => gmdate('Y-m-d H:i:s'), 'rejection_reason' => null];
            } elseif ($user->isAgency() && $status === 'pending') {
                $data['submitted_at'] = gmdate('Y-m-d H:i:s');
            }
            if ($payload['featured'] !== null) {
                $data += $payload['featured'];
            }

            $this->properties->update($id, $data);
            $this->properties->saveAttributeValues($id, $schemaAttributes, $payload['attributes']);
            $this->properties->saveFeatures($id, $payload['features']);
            $this->properties->savePrivateDetails($id, $payload['private'] + array_intersect_key($this->properties->privateDetails($id), array_flip(['owner_name', 'owner_phone', 'owner_email'])));
            $this->syncImages($id, $site, $payload['images'], null, true);
            $this->storeDocument($id, $site, $payload['document'], $property['document_path']);

            // L'équipe modifie directement : une révision d'agence en attente devient caduque
            if ($pendingRevision !== null) {
                $this->discardRevision($pendingRevision, 'superseded', null, $user->id);
            }
            if ($resubmit) {
                $this->properties->recordStatus($id, $status, 'pending', null, $user->id);
            }
        });

        $after = array_intersect_key($payload['fields'], $before);
        $changes = ['before' => [], 'after' => []];
        foreach ($after as $key => $value) {
            if ((string) ($before[$key] ?? '') !== (string) $value) {
                $changes['before'][$key] = $before[$key] ?? null;
                $changes['after'][$key] = $value;
            }
        }
        $this->activity->log('property.updated', $user->id, $site->country->id, 'property', $id, (string) $property['reference'], $changes['after'] !== [] ? $changes : null, $request);

        if ($resubmit) {
            $fresh = $this->reload($id, $site);
            $this->notifyStaff($site, $fresh, 'property.submitted', __('properties.notify.resubmitted_title', ['ref' => $fresh['reference']]), __('properties.notify.submitted_body', ['title' => $fresh['title'], 'agency' => $fresh['agency_name'] ?? '']));

            return self::OUTCOME_RESUBMITTED;
        }

        return self::OUTCOME_UPDATED;
    }

    // Décisions de l'équipe --------------------------------------------------------------------------

    /** @param array<string, mixed> $property */
    public function approve(Request $request, User $user, Site $site, array $property): void
    {
        $this->assertStatus($property, ['pending']);
        $now = gmdate('Y-m-d H:i:s');
        $this->db->transaction(function () use ($user, $property, $now): void {
            $this->properties->update((int) $property['id'], [
                'status' => 'published',
                'published_at' => $property['published_at'] ?? $now,
                'expires_at' => $this->expiryDate(),
                'expiry_reminder_sent_at' => null,
                'rejection_reason' => null,
                'reviewed_by_user_id' => $user->id,
                'reviewed_at' => $now,
            ]);
            $this->properties->recordStatus((int) $property['id'], 'pending', 'published', null, $user->id);
        });

        $this->activity->log('property.approved', $user->id, $site->country->id, 'property', (int) $property['id'], (string) $property['reference'], request: $request);
        $this->notifyOwners($site, $property, 'property.approved', __('properties.notify.approved_title', ['ref' => $property['reference']]), __('properties.notify.approved_body', ['title' => $property['title'], 'days' => (int) $this->settings->get('listing.lifetime_days', 90)]));
    }

    /** @param array<string, mixed> $property */
    public function reject(Request $request, User $user, Site $site, array $property, string $reason): void
    {
        $this->assertStatus($property, ['pending']);
        $this->db->transaction(function () use ($user, $property, $reason): void {
            $this->properties->update((int) $property['id'], [
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'reviewed_by_user_id' => $user->id,
                'reviewed_at' => gmdate('Y-m-d H:i:s'),
            ]);
            $this->properties->recordStatus((int) $property['id'], 'pending', 'rejected', $reason, $user->id);
        });

        $this->activity->log('property.rejected', $user->id, $site->country->id, 'property', (int) $property['id'], (string) $property['reference'], ['after' => ['reason' => $reason]], $request);
        $this->notifyOwners($site, $property, 'property.rejected', __('properties.notify.rejected_title', ['ref' => $property['reference']]), __('properties.notify.rejected_body', ['title' => $property['title'], 'reason' => $reason]));
    }

    /**
     * Applique une révision : champs, critères, équipements et photos remplacent la version en ligne.
     *
     * @param array<string, mixed>             $property
     * @param array<string, mixed>             $revision
     * @param array<int, array<string, mixed>> $schemaAttributes Schéma de la catégorie proposée
     */
    public function approveRevision(Request $request, User $user, Site $site, array $property, array $revision, array $schemaAttributes): void
    {
        $data = $revision['data'];
        $id = (int) $property['id'];

        $this->db->transaction(function () use ($user, $site, $property, $revision, $data, $schemaAttributes, $id): void {
            $fields = array_intersect_key((array) $data['fields'], array_flip(PropertyRepository::EDITABLE_FIELDS));
            $this->properties->update($id, $fields + ['updated_by_user_id' => $user->id, 'reviewed_by_user_id' => $user->id, 'reviewed_at' => gmdate('Y-m-d H:i:s')]);
            $this->properties->saveAttributeValues($id, $schemaAttributes, array_replace([], (array) $data['attributes']));
            $this->properties->saveFeatures($id, array_map('intval', (array) $data['features']));

            // Photos : celles de la révision passent en ligne, les photos retirées sont supprimées
            $this->properties->promoteRevisionImages((int) $revision['id']);
            $kept = [];
            foreach (array_values((array) $data['images']) as $position => $image) {
                $this->properties->updateImage((int) $image['id'], $id, $position, $image['alt'] ?? null);
                $kept[] = (int) $image['id'];
            }
            foreach ($this->properties->images($id) as $image) {
                if (!in_array((int) $image['id'], $kept, true)) {
                    $this->images->deleteVariants((string) $image['path']);
                    $this->properties->deleteImageRow((int) $image['id']);
                }
            }

            if (($data['fields']['document_path'] ?? null) !== $property['document_path']) {
                $this->images->delete($property['document_path']);
            }
            $this->properties->closeRevision((int) $revision['id'], 'approved', null, $user->id);
        });

        $this->activity->log('property.revision_approved', $user->id, $site->country->id, 'property', $id, (string) $property['reference'], request: $request);
        $this->notifyOwners($site, $property, 'property.revision_approved', __('properties.notify.revision_approved_title', ['ref' => $property['reference']]), __('properties.notify.revision_approved_body', ['title' => $data['fields']['title'] ?? $property['title']]));
    }

    /**
     * @param array<string, mixed> $property
     * @param array<string, mixed> $revision
     */
    public function rejectRevision(Request $request, User $user, Site $site, array $property, array $revision, string $reason): void
    {
        $this->db->transaction(fn () => $this->discardRevision($revision, 'rejected', $reason, $user->id));
        $this->activity->log('property.revision_rejected', $user->id, $site->country->id, 'property', (int) $property['id'], (string) $property['reference'], ['after' => ['reason' => $reason]], $request);
        $this->notifyOwners($site, $property, 'property.revision_rejected', __('properties.notify.revision_rejected_title', ['ref' => $property['reference']]), __('properties.notify.rejected_body', ['title' => $property['title'], 'reason' => $reason]));
    }

    /** @param array<string, mixed> $property */
    public function unpublish(Request $request, User $user, Site $site, array $property, ?string $reason): void
    {
        $this->transition($request, $user, $site, $property, ['published'], 'unpublished', ['is_featured' => 0], $reason, 'property.unpublished');
        $this->notifyOwners($site, $property, 'property.unpublished', __('properties.notify.unpublished_title', ['ref' => $property['reference']]), $reason !== null ? __('properties.notify.reason', ['reason' => $reason]) : null);
    }

    /** Remise en ligne par l'équipe (annonce dépubliée ou expirée) : nouvelle durée de vie. */
    public function republish(Request $request, User $user, Site $site, array $property): void
    {
        $this->transition($request, $user, $site, $property, ['unpublished', 'expired'], 'published', [
            'published_at' => $property['published_at'] ?? gmdate('Y-m-d H:i:s'),
            'expires_at' => $this->expiryDate(),
            'expiry_reminder_sent_at' => null,
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => gmdate('Y-m-d H:i:s'),
        ], null, 'property.republished');
    }

    /**
     * Prolongation (agence ou équipe) d'une annonce publiée ou expirée dont le contenu n'a pas changé :
     * pas de nouvelle validation, nouvelle durée de vie à partir d'aujourd'hui.
     */
    public function extend(Request $request, User $user, Site $site, array $property): void
    {
        $this->assertStatus($property, ['published', 'expired']);
        $fields = ['status' => 'published', 'expires_at' => $this->expiryDate(), 'expiry_reminder_sent_at' => null];
        if ($property['status'] === 'expired') {
            $this->transition($request, $user, $site, $property, ['expired'], 'published', $fields, null, 'property.extended');

            return;
        }
        $this->properties->update((int) $property['id'], $fields);
        $this->activity->log('property.extended', $user->id, $site->country->id, 'property', (int) $property['id'], (string) $property['reference'], request: $request);
    }

    /** Archivage : bien vendu ou loué (agence ou équipe). */
    public function archive(Request $request, User $user, Site $site, array $property, string $availability): void
    {
        $this->transition($request, $user, $site, $property, ['published', 'unpublished', 'expired'], 'archived', [
            'availability' => $availability,
            'archived_at' => gmdate('Y-m-d H:i:s'),
            'is_featured' => 0,
        ], null, 'property.archived');
    }

    /** @param array<string, mixed> $property */
    public function setFeatured(Request $request, User $user, Site $site, array $property, bool $featured, ?string $until): void
    {
        $this->properties->update((int) $property['id'], ['is_featured' => $featured ? 1 : 0, 'featured_until' => $featured ? $until : null]);
        $this->activity->log($featured ? 'property.featured' : 'property.unfeatured', $user->id, $site->country->id, 'property', (int) $property['id'], (string) $property['reference'], request: $request);
    }

    /** Suppression logique ; les révisions en attente et leurs photos sont abandonnées. */
    public function delete(Request $request, User $user, Site $site, array $property): void
    {
        $revision = $this->properties->pendingRevision((int) $property['id']);
        $this->db->transaction(function () use ($user, $property, $revision): void {
            if ($revision !== null) {
                $this->discardRevision($revision, 'superseded', null, $user->id);
            }
            $this->properties->softDelete((int) $property['id']);
        });
        $this->activity->log('property.deleted', $user->id, $site->country->id, 'property', (int) $property['id'], $property['reference'] . ' · ' . $property['title'], request: $request);
    }

    // Tâches planifiées ------------------------------------------------------------------------------

    /** @param array<string, mixed> $row */
    public function expire(array $row, ?Site $site): void
    {
        $this->db->transaction(function () use ($row): void {
            $this->properties->update((int) $row['id'], ['status' => 'expired', 'is_featured' => 0]);
            $this->properties->recordStatus((int) $row['id'], 'published', 'expired', null, null);
        });
        $this->activity->log('property.expired', null, (int) $row['country_id'], 'property', (int) $row['id'], (string) $row['reference']);
        $this->notifyOwners($site, $row, 'property.expired', __('properties.notify.expired_title', ['ref' => $row['reference']]), __('properties.notify.expired_body', ['title' => $row['title']]));
    }

    /** @param array<string, mixed> $row */
    public function remind(array $row, ?Site $site): void
    {
        $this->properties->update((int) $row['id'], ['expiry_reminder_sent_at' => gmdate('Y-m-d H:i:s')]);
        $this->notifyOwners($site, $row, 'property.expiring', __('properties.notify.expiring_title', ['ref' => $row['reference']]), __('properties.notify.expiring_body', ['title' => $row['title'], 'date' => substr((string) $row['expires_at'], 0, 10)]));
    }

    // Interne ---------------------------------------------------------------------------------------------

    /**
     * @param array<string, mixed>      $property
     * @param array<string, mixed>      $payload
     * @param array<string, mixed>|null $pendingRevision
     */
    private function storeRevision(User $user, Site $site, array $property, array $payload, ?array $pendingRevision): void
    {
        $id = (int) $property['id'];
        $this->db->transaction(function () use ($user, $site, $property, $payload, $pendingRevision, $id): void {
            $revisionId = $pendingRevision !== null
                ? (int) $pendingRevision['id']
                : $this->properties->createRevision($id, ['fields' => [], 'attributes' => [], 'features' => [], 'images' => []], $user->id);

            $images = $this->syncImages($id, $site, $payload['images'], $revisionId, false);

            // Document : un nouveau PDF est enregistré tout de suite, l'ancien n'est supprimé qu'à l'approbation
            $documentPath = $pendingRevision['data']['fields']['document_path'] ?? $property['document_path'];
            if (($payload['document']['file'] ?? null) !== null) {
                if ($pendingRevision !== null && $documentPath !== $property['document_path']) {
                    $this->images->delete($documentPath);
                }
                $documentPath = $this->images->storePdf($payload['document']['file'], $this->directory($site, $id), 'document');
            } elseif (!empty($payload['document']['remove'])) {
                $documentPath = null;
            }

            $this->properties->savePrivateDetails($id, $payload['private'] + array_intersect_key($this->properties->privateDetails($id), array_flip(['owner_name', 'owner_phone', 'owner_email'])));
            $this->properties->updateRevisionData($revisionId, [
                'fields' => $payload['fields'] + ['document_path' => $documentPath],
                'attributes' => $payload['attributes'],
                'features' => $payload['features'],
                'images' => $images,
            ], $user->id);
        });
    }

    /**
     * Rattache les photos du formulaire à l'annonce.
     *
     * @param list<array{id: ?int, token: ?string, alt: ?string}> $images
     * @param bool $live true : version en ligne (photos retirées supprimées) ; false : révision (photos en ligne intactes)
     * @return list<array{id: int, alt: ?string}> Photos dans l'ordre
     */
    private function syncImages(int $propertyId, Site $site, array $images, ?int $revisionId, bool $live): array
    {
        $existing = array_column($this->properties->images($propertyId, $revisionId), null, 'id');
        $ordered = [];

        foreach ($images as $position => $image) {
            if ($image['id'] !== null && isset($existing[$image['id']])) {
                if ($live || $existing[$image['id']]['revision_id'] !== null) {
                    $this->properties->updateImage($image['id'], $propertyId, $position, $image['alt']);
                }
                $ordered[] = ['id' => $image['id'], 'alt' => $image['alt']];
                continue;
            }
            $pending = $image['token'] !== null ? $this->uploads->get($image['token']) : null;
            if ($pending === null) {
                continue;
            }
            $path = $this->images->moveVariants($pending['path'], $this->directory($site, $propertyId));
            $imageId = $this->properties->insertImage([
                'property_id' => $propertyId,
                'revision_id' => $revisionId,
                'path' => $path,
                'original_name' => mb_substr((string) $pending['original_name'], 0, 255),
                'mime_type' => 'image/webp',
                'width' => $pending['width'],
                'height' => $pending['height'],
                'size_bytes' => $pending['size'],
                'alt_text' => $image['alt'],
                'sort_order' => $position,
            ]);
            $this->uploads->forget((string) $image['token']);
            $ordered[] = ['id' => $imageId, 'alt' => $image['alt']];
        }

        $keptIds = array_column($ordered, 'id');
        foreach ($existing as $imageId => $row) {
            if (in_array((int) $imageId, $keptIds, true)) {
                continue;
            }
            // Version en ligne : suppression ; révision : seules ses propres photos abandonnées sont supprimées
            if ($live || $row['revision_id'] !== null) {
                $this->images->deleteVariants((string) $row['path']);
                $this->properties->deleteImageRow((int) $imageId);
            }
        }

        return $ordered;
    }

    /**
     * @param array{file: array<string, mixed>|null, remove: bool} $document
     */
    private function storeDocument(int $propertyId, Site $site, array $document, ?string $current): void
    {
        if ($document['file'] !== null) {
            $path = $this->images->storePdf($document['file'], $this->directory($site, $propertyId), 'document');
            $this->properties->update($propertyId, ['document_path' => $path]);
            $this->images->delete($current);
        } elseif ($document['remove'] && $current !== null) {
            $this->properties->update($propertyId, ['document_path' => null]);
            $this->images->delete($current);
        }
    }

    /** @param array<string, mixed> $revision */
    private function discardRevision(array $revision, string $status, ?string $reason, ?int $userId): void
    {
        foreach ($this->properties->revisionImages((int) $revision['id']) as $image) {
            $this->images->deleteVariants((string) $image['path']);
            $this->properties->deleteImageRow((int) $image['id']);
        }
        $property = $this->db->selectOne('SELECT document_path FROM properties WHERE id = :id', ['id' => $revision['property_id']]);
        $proposed = $revision['data']['fields']['document_path'] ?? null;
        if ($proposed !== null && $proposed !== ($property['document_path'] ?? null)) {
            $this->images->delete($proposed);
        }
        $this->properties->closeRevision((int) $revision['id'], $status, $reason, $userId);
    }

    /**
     * @param array<string, mixed> $property
     * @param list<string>         $from
     * @param array<string, mixed> $fields
     */
    private function transition(Request $request, User $user, Site $site, array $property, array $from, string $to, array $fields, ?string $reason, string $action): void
    {
        $this->assertStatus($property, $from);
        $this->db->transaction(function () use ($user, $property, $to, $fields, $reason): void {
            $this->properties->update((int) $property['id'], ['status' => $to] + $fields);
            $this->properties->recordStatus((int) $property['id'], (string) $property['status'], $to, $reason, $user->id);
        });
        $this->activity->log($action, $user->id, $site->country->id, 'property', (int) $property['id'], (string) $property['reference'], $reason !== null ? ['after' => ['reason' => $reason]] : null, $request);
    }

    /**
     * @param array<string, mixed> $property
     * @param list<string>         $allowed
     */
    private function assertStatus(array $property, array $allowed): void
    {
        if (!in_array($property['status'], $allowed, true)) {
            throw new RuntimeException(sprintf('Transition impossible depuis « %s ».', $property['status']));
        }
    }

    private function expiryDate(): string
    {
        return gmdate('Y-m-d H:i:s', time() + max(1, (int) $this->settings->get('listing.lifetime_days', 90)) * 86400);
    }

    private function directory(Site $site, int $propertyId): string
    {
        return strtolower($site->country->iso2) . '/annonces/' . $propertyId;
    }

    /** @return array<string, mixed> */
    private function reload(int $id, Site $site): array
    {
        $reference = (string) $this->db->scalar('SELECT reference FROM properties WHERE id = :id', ['id' => $id]);

        return $this->properties->findByReference($reference, $site->country->id) ?? throw new RuntimeException('Annonce introuvable après enregistrement.');
    }

    /** @param array<string, mixed> $property */
    private function notifyStaff(Site $site, array $property, string $type, string $title, ?string $body): void
    {
        try {
            $recipients = $this->notifier->staffRecipients($site->country->id);
            $this->notifier->notify($recipients['all'], $type, $title, $body, '/cmsadmin/annonces/' . $property['reference'], $site, $recipients['email']);
        } catch (Throwable $exception) {
            $this->logger->exception($exception, ['notification' => $type]);
        }
    }

    /** @param array<string, mixed> $property */
    private function notifyOwners(?Site $site, array $property, string $type, string $title, ?string $body): void
    {
        try {
            $recipients = $this->notifier->propertyRecipients($property);
            $this->notifier->notify($recipients, $type, $title, $body, '/cmsadmin/annonces/' . $property['reference'], $site, $recipients);
        } catch (Throwable $exception) {
            $this->logger->exception($exception, ['notification' => $type]);
        }
    }
}
