<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Site;
use App\Services\ActivityLogger;
use App\Services\Auth;
use App\Services\CatalogRepository;
use App\Services\CountryRepository;
use App\Services\GeoRepository;
use App\Services\LoginThrottle;
use App\Services\Mailer;
use App\Services\PasswordHasher;
use App\Services\PasswordReset;
use App\Services\RateLimiter;
use App\Services\Settings;
use App\Services\SiteRepository;
use App\Services\UserRepository;
use Closure;
use LogicException;
use Throwable;

/**
 * Noyau de l'application : services partagés (créés à la demande) et traitement d'une requête.
 *
 * Cycle : requête → redirection des « / » finaux → middlewares globaux (site courant, CSRF)
 *         → routeur → middlewares de la route → contrôleur → réponse (+ en-têtes de sécurité, page d'erreur si exception).
 */
final class App
{
    private static ?self $instance = null;

    /** Middlewares appliqués à toute requête, avant le routage, dans l'ordre. */
    private const GLOBAL_MIDDLEWARE = [
        \App\Middlewares\SiteResolver::class,
        \App\Middlewares\VerifyCsrfToken::class,
    ];

    public readonly Config $config;
    public readonly Router $router;
    public readonly ErrorHandler $errors;

    private ?Database $database = null;
    private ?Session $session = null;
    private ?Csrf $csrf = null;
    private ?View $view = null;
    private ?Translator $translator = null;
    private ?Logger $logger = null;
    private ?Request $request = null;
    private ?Cache $cache = null;
    private ?SiteRepository $sites = null;
    private ?Site $site = null;
    private ?Settings $settings = null;
    /** @var array<string, object> Services du back-office créés à la demande */
    private array $services = [];

    public function __construct(public readonly string $root)
    {
        $this->config = new Config($root . '/config');
        $this->router = new Router();
        $this->errors = new ErrorHandler($this->logger(), (bool) $this->config->get('app.debug'), $this->view());
        self::$instance = $this;
    }

    public static function instance(): self
    {
        return self::$instance ?? throw new LogicException('Application non initialisée.');
    }

    public function db(): Database
    {
        return $this->database ??= new Database((array) $this->config->get('database'));
    }

    public function session(): Session
    {
        return $this->session ??= new Session((array) $this->config->get('app.session'), $this->request?->isSecure() ?? false);
    }

    public function csrf(): Csrf
    {
        return $this->csrf ??= new Csrf($this->session());
    }

    public function view(): View
    {
        return $this->view ??= new View($this->root . '/app/Views');
    }

    public function translator(): Translator
    {
        return $this->translator ??= new Translator(
            $this->root . '/lang',
            (string) $this->config->get('app.locale', 'fr'),
            (string) $this->config->get('app.fallback_locale', 'fr')
        );
    }

    public function logger(): Logger
    {
        return $this->logger ??= new Logger((string) $this->config->get('app.log.path', $this->root . '/storage/logs'));
    }

    public function cache(): Cache
    {
        return $this->cache ??= new Cache((string) $this->config->get('app.cache.path', $this->root . '/storage/cache'));
    }

    public function sites(): SiteRepository
    {
        $ttl = $this->config->get('app.cache.sites_ttl');

        return $this->sites ??= new SiteRepository($this->db(), $this->cache(), $ttl === null ? null : (int) $ttl);
    }

    /** Site courant (null avant résolution, en CLI ou si le domaine est inconnu). */
    public function site(): ?Site
    {
        return $this->site;
    }

    public function setSite(Site $site): void
    {
        $this->site = $site;
        $this->settings = null;
    }

    /** Paramètres effectifs : globaux, surchargés par ceux du site courant. */
    public function settings(): Settings
    {
        return $this->settings ??= new Settings($this->sites()->settingsFor($this->site?->id));
    }

    public function users(): UserRepository
    {
        return $this->service(UserRepository::class, fn () => new UserRepository($this->db()));
    }

    public function hasher(): PasswordHasher
    {
        return $this->service(PasswordHasher::class, fn () => new PasswordHasher((int) $this->config->get('auth.password_min_length', 12)));
    }

    public function activity(): ActivityLogger
    {
        return $this->service(ActivityLogger::class, fn () => new ActivityLogger($this->db(), $this->logger()));
    }

    public function mailer(): Mailer
    {
        return $this->service(Mailer::class, fn () => new Mailer((array) $this->config->get('mail')));
    }

    public function geo(): GeoRepository
    {
        return $this->service(GeoRepository::class, fn () => new GeoRepository($this->db()));
    }

    public function countries(): CountryRepository
    {
        return $this->service(CountryRepository::class, fn () => new CountryRepository($this->db()));
    }

    public function catalog(): CatalogRepository
    {
        return $this->service(CatalogRepository::class, fn () => new CatalogRepository($this->db()));
    }

    public function rateLimiter(): RateLimiter
    {
        return $this->service(RateLimiter::class, fn () => new RateLimiter($this->cache()));
    }

    public function auth(): Auth
    {
        return $this->service(Auth::class, fn () => new Auth(
            $this->db(),
            $this->session(),
            $this->users(),
            $this->hasher(),
            new LoginThrottle(
                $this->db(),
                (int) $this->settings()->get('security.login_max_attempts', 5),
                (int) $this->settings()->get('security.login_lockout_minutes', 15),
                (int) $this->config->get('auth.ip_failure_limit', 30),
            ),
            $this->activity(),
            (array) $this->config->get('auth'),
        ));
    }

    public function passwordReset(): PasswordReset
    {
        return $this->service(PasswordReset::class, fn () => new PasswordReset(
            $this->db(),
            $this->users(),
            $this->hasher(),
            $this->mailer(),
            $this->view(),
            $this->activity(),
            $this->logger(),
            (int) $this->config->get('auth.reset_expires', 60),
        ));
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @param Closure(): T    $factory
     * @return T
     */
    private function service(string $id, Closure $factory): object
    {
        return $this->services[$id] ??= $factory();
    }

    public function request(): ?Request
    {
        return $this->request;
    }

    /** Charge un fichier de routes : il retourne une fonction recevant le routeur. */
    public function loadRoutes(string $file): void
    {
        $register = require $file;
        $register($this->router, $this);
    }

    public function run(): void
    {
        $request = Request::fromGlobals((string) $this->config->get('app.base_path', ''));
        $this->handle($request)->send(!$request->isMethod('HEAD'));
    }

    public function handle(Request $request): Response
    {
        $this->request = $request;
        $this->errors->setRequest($request);
        // État propre à chaque requête (plusieurs requêtes peuvent être traitées par la même instance : tests)
        $this->site = null;
        $this->settings = null;
        unset($this->services[Auth::class]);
        $this->translator()->setLocale((string) $this->config->get('app.locale', 'fr'));

        try {
            $response = $this->dispatch($request);
        } catch (Throwable $exception) {
            $response = $this->errors->render($exception);
        }

        return $this->withSecurityHeaders($request, $response);
    }

    private function dispatch(Request $request): Response
    {
        $path = $request->path();

        // Une seule URL par page (SEO) : /louer/ → /louer
        if ($path !== '/' && str_ends_with($path, '/') && $request->isMethod('GET')) {
            $query = $request->server('QUERY_STRING', '');

            return Response::redirect(url(rtrim($path, '/')) . ($query !== '' ? '?' . $query : ''), 301);
        }

        // Session reprise uniquement si le navigateur en a déjà une (flash, jeton CSRF, connexion)
        $this->session()->resumeIfExists($request->cookie((string) $this->config->get('app.session.name')));

        $core = function (Request $request): Response {
            $route = $this->router->dispatch($request->method(), $request->path());
            foreach ($route['params'] as $name => $value) {
                $request = $request->withAttribute($name, $value);
            }

            $handler = fn (Request $request): Response => $this->callHandler($route['handler'], $request, $route['params']);

            return $this->pipeline($route['middleware'], $handler)($request);
        };
        $pipeline = $this->pipeline(self::GLOBAL_MIDDLEWARE, $core);

        return $pipeline($request);
    }

    /**
     * Enchaîne des middlewares autour de $core (le premier de la liste s'exécute en premier).
     *
     * @param list<string>              $middleware
     * @param Closure(Request): Response $core
     * @return Closure(Request): Response
     */
    private function pipeline(array $middleware, Closure $core): Closure
    {
        return array_reduce(
            array_reverse($middleware),
            fn (Closure $next, string $definition): Closure => fn (Request $request): Response => $this->callMiddleware($definition, $request, $next),
            $core
        );
    }

    /** @param array<string, string> $params */
    private function callHandler(array|Closure $handler, Request $request, array $params): Response
    {
        $this->request = $request;

        if ($handler instanceof Closure) {
            $result = $handler($request, ...$params);
        } else {
            [$class, $method] = $handler;
            $result = (new $class($this))->{$method}($request, ...$params);
        }

        return match (true) {
            $result instanceof Response => $result,
            is_string($result) => Response::html($result),
            default => throw new LogicException('Un contrôleur doit retourner une Response ou une chaîne HTML.'),
        };
    }

    /** @param Closure(Request): Response $next */
    private function callMiddleware(string $definition, Request $request, Closure $next): Response
    {
        [$class, $arguments] = array_pad(explode(':', $definition, 2), 2, '');
        if (!class_exists($class)) {
            throw new LogicException("Middleware introuvable : {$class} (utiliser Classe::class . ':arguments').");
        }
        $middleware = new $class($this);
        if (!$middleware instanceof Middleware) {
            throw new LogicException("{$class} n'implémente pas App\\Core\\Middleware.");
        }

        return $middleware->handle($request, $next, ...($arguments === '' ? [] : explode(',', $arguments)));
    }

    private function withSecurityHeaders(Request $request, Response $response): Response
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), payment=(), usb=()',
        ];
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }
        // Back-office, pré-production et postes locaux : jamais indexés
        if ($request->isCmsadmin() || ($this->site !== null && !$this->site->isProductionHost())) {
            $headers['X-Robots-Tag'] = 'noindex, nofollow';
        }
        // Pages personnalisées (session ouverte) : jamais mises en cache par un proxy
        if ($this->session?->isStarted() === true && !$response->hasHeader('Cache-Control')) {
            $headers['Cache-Control'] = 'private, no-store';
        }

        foreach ($headers as $name => $value) {
            if (!$response->hasHeader($name)) {
                $response->setHeader($name, $value);
            }
        }

        return $response;
    }
}
