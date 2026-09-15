<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use Throwable;

/**
 * Journal des actions du back-office (table activity_logs) : connexions, créations, validations…
 * Un échec d'écriture du journal est consigné dans storage/logs sans interrompre l'action.
 */
final class ActivityLogger
{
    public function __construct(
        private readonly Database $db,
        private readonly Logger $logger,
    ) {
    }

    /**
     * @param string                    $action     Verbe pointé : user.login, property.approved, agency.created…
     * @param array{before?: array<string, mixed>, after?: array<string, mixed>}|null $changes
     */
    public function log(
        string $action,
        ?int $userId,
        ?int $countryId = null,
        ?string $entityType = null,
        int|string|null $entityId = null,
        ?string $description = null,
        ?array $changes = null,
        ?Request $request = null,
    ): void {
        try {
            $this->db->execute(
                'INSERT INTO activity_logs (user_id, country_id, action, entity_type, entity_id, description, changes, ip, user_agent)
                 VALUES (:user_id, :country_id, :action, :entity_type, :entity_id, :description, :changes, :ip, :user_agent)',
                [
                    'user_id' => $userId,
                    'country_id' => $countryId,
                    'action' => $action,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId !== null ? (int) $entityId : null,
                    'description' => $description !== null ? mb_substr($description, 0, 255) : null,
                    'changes' => $changes !== null ? json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
                    'ip' => $request !== null ? IpAddress::toBinary($request->ip()) : null,
                    'user_agent' => $request !== null ? mb_substr($request->userAgent(), 0, 255) : null,
                ]
            );
        } catch (Throwable $exception) {
            $this->logger->exception($exception, ['activity' => $action]);
        }
    }
}
