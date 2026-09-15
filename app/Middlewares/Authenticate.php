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
 * Réserve les pages du back-office aux comptes connectés.
 *
 * - non connecté : redirection vers la connexion (page demandée mémorisée), 401 en AJAX ;
 * - compte d'un autre pays que le site courant (Admin Pays, agence) : 403 ;
 * - mot de passe provisoire : redirection vers le changement obligatoire (sauf pages autorisées).
 */
final class Authenticate implements Middleware
{
    /** Routes accessibles avec un mot de passe provisoire. */
    private const ALLOWED_WITH_TEMPORARY_PASSWORD = ['/cmsadmin/mot-de-passe/changer', '/cmsadmin/deconnexion'];

    public function __construct(private readonly App $app)
    {
    }

    public function handle(Request $request, Closure $next, string ...$arguments): Response
    {
        $auth = $this->app->auth();
        $user = $auth->user($request);

        if ($user === null) {
            if ($request->isAjax()) {
                throw new HttpException(401);
            }
            if ($request->isMethod('GET')) {
                $query = (string) $request->server('QUERY_STRING', '');
                $auth->setIntendedUrl($request->path() . ($query !== '' ? '?' . $query : ''));
            }

            return Response::redirect(route('cmsadmin.login'));
        }

        $site = $this->app->site();
        if ($site === null || !$user->canAccessCountry($site->country->id)) {
            throw new HttpException(403);
        }

        if ($user->mustChangePassword && !in_array($request->path(), self::ALLOWED_WITH_TEMPORARY_PASSWORD, true)) {
            return Response::redirect(route('cmsadmin.password.change'));
        }

        return $next($request->withAttribute('user', $user));
    }
}
