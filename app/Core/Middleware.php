<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

/**
 * Middleware : traite la requête avant (et/ou après) le contrôleur.
 *
 * Déclaration dans les routes : Classe::class ou 'Classe:arg1,arg2' (arguments passés à handle()).
 */
interface Middleware
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string ...$arguments): Response;
}
