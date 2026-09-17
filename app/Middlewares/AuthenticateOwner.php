<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\App;
use App\Core\Exceptions\HttpException;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Réserve l'espace propriétaire (/mon-espace) aux particuliers connectés.
 *
 * Garde « owner » d'Auth : session distincte du back-office. Un membre de l'équipe ou un partenaire
 * connecté à /cmsadmin n'est pas connecté ici, et un particulier n'est jamais admis au back-office.
 *
 * - non connecté : redirection vers la connexion de l'espace (page demandée mémorisée) ;
 * - compte d'un autre pays que le site : 403.
 */
final class AuthenticateOwner implements Middleware
{
    public function __construct(private readonly App $app)
    {
    }

    public function handle(Request $request, Closure $next, string ...$arguments): Response
    {
        $auth = $this->app->ownerAuth();
        $user = $auth->user($request);

        if ($user === null) {
            if ($request->isMethod('GET')) {
                $query = (string) $request->server('QUERY_STRING', '');
                $auth->setIntendedUrl($request->path() . ($query !== '' ? '?' . $query : ''));
            }

            return Response::redirect(url('mon-espace/connexion'));
        }

        $site = $this->app->site();
        if (!$user->isOwner() || $site === null || !$user->canAccessCountry($site->country->id)) {
            throw new HttpException(403);
        }

        return $next($request->withAttribute('owner', $user));
    }
}
