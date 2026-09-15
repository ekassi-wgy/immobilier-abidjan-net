<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;
use Closure;
use InvalidArgumentException;

/**
 * Routeur : routes nommées, paramètres ({slug}, {id:\d+}), groupes (préfixe, nom, middlewares).
 *
 *   $router->get('/annonces/{slug}', [PropertyController::class, 'show'], 'property.show');
 *   $router->group(['prefix' => '/cmsadmin', 'as' => 'cmsadmin.', 'middleware' => [Auth::class]], function (Router $r) { … });
 */
final class Router
{
    /** @var list<array{methods: list<string>, path: string, regex: string, handler: array|Closure, name: ?string, middleware: list<string>}> */
    private array $routes = [];

    /** @var array<string, int> Nom → index dans $routes */
    private array $names = [];

    /** @var list<array{prefix: string, as: string, middleware: list<string>}> */
    private array $groupStack = [];

    public function get(string $path, array|Closure $handler, ?string $name = null): self
    {
        return $this->add(['GET', 'HEAD'], $path, $handler, $name);
    }

    public function post(string $path, array|Closure $handler, ?string $name = null): self
    {
        return $this->add(['POST'], $path, $handler, $name);
    }

    /** @param list<string> $methods */
    public function match(array $methods, string $path, array|Closure $handler, ?string $name = null): self
    {
        $methods = array_map('strtoupper', $methods);
        if (in_array('GET', $methods, true) && !in_array('HEAD', $methods, true)) {
            $methods[] = 'HEAD';
        }

        return $this->add($methods, $path, $handler, $name);
    }

    /**
     * @param array{prefix?: string, as?: string, middleware?: list<string>} $attributes
     * @param callable(Router): void $routes
     */
    public function group(array $attributes, callable $routes): void
    {
        $parent = end($this->groupStack) ?: ['prefix' => '', 'as' => '', 'middleware' => []];

        $this->groupStack[] = [
            'prefix' => $parent['prefix'] . '/' . trim($attributes['prefix'] ?? '', '/'),
            'as' => $parent['as'] . ($attributes['as'] ?? ''),
            'middleware' => [...$parent['middleware'], ...($attributes['middleware'] ?? [])],
        ];

        try {
            $routes($this);
        } finally {
            array_pop($this->groupStack);
        }
    }

    /**
     * Middlewares ajoutés à la dernière route déclarée.
     *
     * @param string ...$middleware Classes implémentant App\Core\Middleware
     */
    public function middleware(string ...$middleware): self
    {
        $index = array_key_last($this->routes);
        if ($index !== null) {
            $this->routes[$index]['middleware'] = [...$this->routes[$index]['middleware'], ...$middleware];
        }

        return $this;
    }

    /**
     * @return array{handler: array|Closure, params: array<string, string>, middleware: list<string>, name: ?string}
     *
     * @throws HttpException 404 si aucune route, 405 si la méthode n'est pas autorisée
     */
    public function dispatch(string $method, string $path): array
    {
        $allowed = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            if (!in_array($method, $route['methods'], true)) {
                $allowed = [...$allowed, ...$route['methods']];
                continue;
            }

            return [
                'handler' => $route['handler'],
                'params' => array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY),
                'middleware' => $route['middleware'],
                'name' => $route['name'],
            ];
        }

        if ($allowed !== []) {
            throw new HttpException(405, headers: ['Allow' => implode(', ', array_unique($allowed))]);
        }

        throw new HttpException(404);
    }

    /**
     * Chemin d'une route nommée (sans préfixe d'installation).
     *
     * @param array<string, string|int> $params Les paramètres non utilisés dans le chemin passent en chaîne de requête
     */
    public function path(string $name, array $params = []): string
    {
        if (!isset($this->names[$name])) {
            throw new InvalidArgumentException("Route inconnue : {$name}");
        }

        $path = (string) preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::[^}]+)?\}#',
            static function (array $match) use (&$params, $name): string {
                if (!array_key_exists($match[1], $params)) {
                    throw new InvalidArgumentException("Paramètre « {$match[1]} » manquant pour la route {$name}");
                }
                $value = rawurlencode((string) $params[$match[1]]);
                unset($params[$match[1]]);

                return $value;
            },
            $this->routes[$this->names[$name]]['path']
        );

        return $params === [] ? $path : $path . '?' . http_build_query($params);
    }

    public function has(string $name): bool
    {
        return isset($this->names[$name]);
    }

    /** @param list<string> $methods */
    private function add(array $methods, string $path, array|Closure $handler, ?string $name): self
    {
        $group = end($this->groupStack) ?: ['prefix' => '', 'as' => '', 'middleware' => []];
        $path = '/' . trim($group['prefix'] . '/' . trim($path, '/'), '/');
        $path = (string) preg_replace('#/+#', '/', $path);

        $this->routes[] = [
            'methods' => $methods,
            'path' => $path,
            'regex' => $this->compile($path),
            'handler' => $handler,
            'name' => $name !== null ? $group['as'] . $name : null,
            'middleware' => $group['middleware'],
        ];

        if ($name !== null) {
            $fullName = $group['as'] . $name;
            if (isset($this->names[$fullName])) {
                throw new InvalidArgumentException("Nom de route déjà utilisé : {$fullName}");
            }
            $this->names[$fullName] = array_key_last($this->routes);
        }

        return $this;
    }

    private function compile(string $path): string
    {
        $regex = '';
        $offset = 0;

        preg_match_all('#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}#', $path, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
        foreach ($matches as $match) {
            $regex .= preg_quote(substr($path, $offset, $match[0][1] - $offset), '#');
            $regex .= '(?P<' . $match[1][0] . '>' . ($match[2][0] ?? '[^/]+') . ')';
            $offset = $match[0][1] + strlen($match[0][0]);
        }
        $regex .= preg_quote(substr($path, $offset), '#');

        return '#^' . $regex . '$#u';
    }
}
