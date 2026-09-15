<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\App;
use App\Core\Exceptions\HttpException;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use Closure;
use LogicException;

/**
 * Contrôle du rôle, côté serveur : 'RequireRole:super_admin' · 'RequireRole:staff' · 'RequireRole:super_admin,agency'.
 * Alias : staff (Super Admin + Admin Pays), agency (responsable + agent d'agence). À placer après Authenticate.
 * Ne remplace pas le contrôle de propriété (country_id, agency_id) à faire dans chaque action.
 */
final class RequireRole implements Middleware
{
    public function __construct(private readonly App $app)
    {
    }

    public function handle(Request $request, Closure $next, string ...$arguments): Response
    {
        $user = $request->attribute('user');
        if (!$user instanceof User) {
            throw new LogicException('RequireRole doit être placé après Authenticate.');
        }
        if ($arguments === [] || !$user->hasRole(...$arguments)) {
            throw new HttpException(403);
        }

        return $next($request);
    }
}
