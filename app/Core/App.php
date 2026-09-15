<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;
use Closure;
use LogicException;
use Throwable;

/**
 * Noyau de l'application : services partagés (créés à la demande) et traitement d'une requête.
 *
 * Cycle : requête → redirection des « / » finaux → routeur → middlewares → contrôleur → réponse
 *         (+ en-têtes de sécurité, page d'erreur si exception).
 */
final class App
{
    private static ?self $instance = null;

    /** Middlewares appliqués à toutes les routes, dans l'ordre. */
    private const GLOBAL_MIDDLEWARE = [
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

        $route = $this->router->dispatch($request->method(), $path);
        foreach ($route['params'] as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }
        $this->request = $request;

        $core = fn (Request $request): Response => $this->callHandler($route['handler'], $request, $route['params']);
        $pipeline = array_reduce(
            array_reverse([...self::GLOBAL_MIDDLEWARE, ...$route['middleware']]),
            fn (Closure $next, string $middleware): Closure => fn (Request $request): Response => $this->callMiddleware($middleware, $request, $next),
            $core
        );

        return $pipeline($request);
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
        if ($request->isCmsadmin()) {
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
