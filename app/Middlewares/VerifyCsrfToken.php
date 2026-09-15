<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\App;
use App\Core\Csrf;
use App\Core\Exceptions\HttpException;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Refuse toute requête d'écriture (POST, PUT, PATCH, DELETE) sans jeton CSRF valide → 419.
 */
final class VerifyCsrfToken implements Middleware
{
    public function __construct(private readonly App $app)
    {
    }

    public function handle(Request $request, Closure $next, string ...$arguments): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $token = $request->input(Csrf::FIELD) ?? $request->header('X-CSRF-Token');
        if (!$this->app->csrf()->validate(is_string($token) ? $token : null)) {
            throw new HttpException(419, 'Jeton CSRF absent ou invalide');
        }

        return $next($request);
    }
}
