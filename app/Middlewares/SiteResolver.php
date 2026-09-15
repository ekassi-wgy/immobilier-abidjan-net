<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\App;
use App\Core\Exceptions\HttpException;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Models\Site;
use Closure;

/**
 * Résout le site courant depuis le nom d'hôte (HTTP_HOST sans le port) → site → pays.
 *
 * - domaine inconnu → 404 (l'en-tête Host n'est jamais utilisé sans avoir été reconnu) ;
 * - domaines « local » acceptés uniquement si APP_ENV=local ;
 * - alias de production non principal → 301 vers le domaine principal ;
 * - site désactivé → 404 ; en maintenance → 503 sur le site public (le back-office reste accessible) ;
 * - langue de l'interface = langue par défaut du site ;
 * - domaines hors production : noindex (posé par App avec les en-têtes de sécurité).
 */
final class SiteResolver implements Middleware
{
    public function __construct(private readonly App $app)
    {
    }

    public function handle(Request $request, Closure $next, string ...$arguments): Response
    {
        $host = $request->host();
        $site = $host !== '' ? $this->app->sites()->findByHost($host, $this->app->config->get('app.env') === 'local') : null;

        if ($site === null || $site->status === Site::STATUS_DISABLED) {
            throw new HttpException(404, 'Site inconnu ou désactivé : ' . $host);
        }

        $this->app->setSite($site);
        if ($this->app->translator()->locale() !== $site->defaultLocale) {
            $this->app->translator()->setLocale($site->defaultLocale);
        }

        // Une seule adresse publique par site (SEO) : les alias de production redirigent vers le domaine principal
        if ($site->isProductionHost() && $site->primaryHost !== null && $host !== $site->primaryHost
            && in_array($request->method(), ['GET', 'HEAD'], true)) {
            $uri = (string) $request->server('REQUEST_URI', '/');

            return Response::redirect(($request->isSecure() ? 'https://' : 'http://') . $site->primaryHost . $uri, 301);
        }

        if ($site->isInMaintenance() && !$request->isCmsadmin()) {
            throw new HttpException(503, headers: ['Retry-After' => '3600']);
        }

        return $next($request->withAttribute('site', $site));
    }
}
