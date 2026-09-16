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
 * Redirections gérées en base (lot 2.1), appliquées avant le routage.
 *
 * Elles servent à ne pas perdre le référencement d'une URL changée : slug d'annonce refait,
 * page déplacée, ancienne arborescence. Une entrée en 410 marque une page définitivement retirée.
 *
 * Ce middleware s'exécute après `SiteResolver` (il a besoin du site) et ne touche ni le
 * back-office — que les moteurs ne voient pas — ni autre chose qu'une lecture. La table est lue
 * à chaque requête : elle est mise en cache (`SeoRepository::flush()` après toute écriture).
 */
final class ApplyRedirects implements Middleware
{
    public function __construct(private readonly App $app)
    {
    }

    public function handle(Request $request, Closure $next, string ...$arguments): Response
    {
        $site = site();
        if ($site === null || $request->isCmsadmin() || !in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $seo = $this->app->seo();
        $redirect = $seo->redirect($request->path(), $site->id);
        if ($redirect === null) {
            return $next($request);
        }

        $seo->recordHit($redirect['id']);

        if ($redirect['code'] === 410) {
            throw new HttpException(410);
        }

        // La cible peut être une URL absolue (migration depuis un ancien domaine) ou un chemin.
        $target = preg_match('#^https?://#i', $redirect['target']) === 1
            ? $redirect['target']
            : url(ltrim($redirect['target'], '/'));

        $query = (string) $request->server('QUERY_STRING', '');

        return Response::redirect($target . ($query !== '' && !str_contains($target, '?') ? '?' . $query : ''), $redirect['code']);
    }
}
