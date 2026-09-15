<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\App;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Écrans réservés aux personnes non connectées (connexion, mot de passe oublié) : un compte connecté va au tableau de bord.
 */
final class RedirectIfAuthenticated implements Middleware
{
    public function __construct(private readonly App $app)
    {
    }

    public function handle(Request $request, Closure $next, string ...$arguments): Response
    {
        if ($this->app->auth()->check($request)) {
            return Response::redirect(route('cmsadmin.dashboard'));
        }

        return $next($request);
    }
}
