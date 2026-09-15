<?php

declare(strict_types=1);

namespace App\Controllers\Cmsadmin;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;

/**
 * Notifications du back-office (cloche de la barre supérieure).
 */
final class NotificationController extends Controller
{
    /** Ouvre une notification : elle est marquée comme lue, puis l'utilisateur est envoyé vers la page concernée. */
    public function open(Request $request, string $id): Response
    {
        $notification = $this->app->notifier()->open((int) $id, $this->user($request)->id) ?? throw new HttpException(404);
        $link = (string) $notification['link_url'];

        return Response::redirect(url(preg_match('#^/cmsadmin/[A-Za-z0-9/_\-.]*$#', $link) === 1 ? $link : '/cmsadmin'));
    }

    public function markAllRead(Request $request): Response
    {
        $this->app->notifier()->markAllRead($this->user($request)->id);

        return $this->backTo($request, '/cmsadmin');
    }
}
