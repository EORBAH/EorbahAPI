<?php

namespace Eorbahapi;

use Eorbahapi\Dependency\DependencyResolver;

/**
 * Routeur HTTP autonome.
 *
 * Il stocke les routes (statiques et paramétrées), sait reconnaître la route
 * correspondant à une requête et produire une `Response` à partir du retour du
 * handler. `EorbahAPI` l'utilise, mais il est utilisable seul — par exemple
 * pour écrire un autre runner :
 *
 *     $router = new APIRouter('/api');
 *     $router->get('/users/{id}', fn (Request $r) => ['id' => $r->param('id')]);
 *
 *     $response = $router->dispatch(new Request(server: ['REQUEST_URI' => '/api/users/7']));
 *     $response->send();
 *
 * Un middleware est instancié à la demande et doit exposer `__invoke()`,
 * `process()` ou `handle()` avec la signature `($request, $response, $next)`.
 */
class APIRouter
{
    /**
     * Routes statiques : [méthode => [chemin => ['callback' => ..., 'middlewares' => [...]]]].
     *
     * @var array<string, array<string, array{callback: callable, middlewares: array}>>
     */
    public array $routes = [];

    /**
     * Routes paramétrées : [méthode => [['route', 'pattern', 'callback', 'middlewares'], ...]].
     *
     * @var array<string, list<array{route: string, pattern: string, callback: callable, middlewares: array}>>
     */
    public array $dynamicRoutes = [];

    public function __construct(
        public readonly ?string $prefix = '',
        public readonly array $tags = []
    ) {}

    public function get(string $route, callable $callback, array $middlewares = []): void
    {
        $this->add_api_route($route, ['GET'], $callback, $middlewares);
    }

    public function post(string $route, callable $callback, array $middlewares = []): void
    {
        $this->add_api_route($route, ['POST'], $callback, $middlewares);
    }

    public function put(string $route, callable $callback, array $middlewares = []): void
    {
        $this->add_api_route($route, ['PUT'], $callback, $middlewares);
    }

    public function patch(string $route, callable $callback, array $middlewares = []): void
    {
        $this->add_api_route($route, ['PATCH'], $callback, $middlewares);
    }

    public function delete(string $route, callable $callback, array $middlewares = []): void
    {
        $this->add_api_route($route, ['DELETE'], $callback, $middlewares);
    }

    public function head(string $route, callable $callback, array $middlewares = []): void
    {
        $this->add_api_route($route, ['HEAD'], $callback, $middlewares);
    }

    public function options(string $route, callable $callback, array $middlewares = []): void
    {
        $this->add_api_route($route, ['OPTIONS'], $callback, $middlewares);
    }

    /**
     * Enregistre une route pour une ou plusieurs méthodes HTTP.
     *
     * @param string          $route       Chemin, éventuellement paramétré (`/users/{id}`).
     * @param string|string[] $methods     Méthodes HTTP (`'GET|POST'` accepté).
     * @param callable        $callback    Handler.
     * @param array           $middlewares Middlewares de la route.
     */
    public function add_api_route(string $route, string|array $methods, callable $callback, array $middlewares = []): void
    {
        $path = $this->normalizePath(($this->prefix ?? '') . $route);
        $methods = is_array($methods) ? $methods : explode('|', $methods);
        $middlewares = array_values(array_map([self::class, 'normalizeMiddleware'], $middlewares));

        foreach ($methods as $method) {
            $method = strtoupper(trim((string) $method));

            if ($method === '') {
                continue;
            }

            if ($this->hasParameters($path)) {
                $this->dynamicRoutes[$method][] = [
                    'route' => $path,
                    'pattern' => $this->buildPattern($path),
                    'callback' => $callback,
                    'middlewares' => $middlewares,
                ];
            } else {
                $this->routes[$method][$path] = [
                    'callback' => $callback,
                    'middlewares' => $middlewares,
                ];
            }
        }
    }


    /**
     * Cherche la route correspondant à une URI et une méthode.
     *
     * @return array{callback: callable, middlewares: array, params: array<string, string>}|null
     */
    public function match(string $uri, string $method): ?array
    {
        $method = strtoupper($method);
        $path = $this->normalizePath($uri);

        if (isset($this->routes[$method][$path])) {
            $route = $this->routes[$method][$path];

            return [
                'callback' => $route['callback'],
                'middlewares' => $route['middlewares'] ?? [],
                'params' => [],
            ];
        }

        foreach ($this->dynamicRoutes[$method] ?? [] as $route) {
            if (preg_match($route['pattern'], $path, $matches) === 1) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }

                return [
                    'callback' => $route['callback'],
                    'middlewares' => $route['middlewares'],
                    'params' => $params,
                ];
            }
        }

        return null;
    }

    /**
     * Routes enregistrées pour une méthode (statiques et paramétrées).
     *
     * @return list<array{route: string, callback: callable, middlewares: array}>
     */
    public function routesFor(string $method): array
    {
        $method = strtoupper($method);
        $routes = [];

        foreach ($this->routes[$method] ?? [] as $path => $route) {
            $routes[] = ['route' => (string) $path, 'callback' => $route['callback'], 'middlewares' => $route['middlewares'] ?? []];
        }

        foreach ($this->dynamicRoutes[$method] ?? [] as $route) {
            $routes[] = ['route' => $route['route'], 'callback' => $route['callback'], 'middlewares' => $route['middlewares']];
        }

        return $routes;
    }

    /**
     * Exécute la route correspondant à la requête et retourne sa réponse.
     *
     * Utilisable tel quel par un runner personnalisé : aucun envoi n'est fait,
     * l'appelant décide de `send()` ou non. Les middlewares de la route sont
     * appliqués.
     */
    public function dispatch(
        Request $request,
        ?Response $response = null,
        ?DependencyResolver $resolver = null
    ): ?Response {
        $match = $this->match($request->path(), $request->method());

        if ($match === null) {
            return null;
        }

        $response ??= new Response();
        $request->params(path: $match['params']);

        $resolver ??= new DependencyResolver($request, $response);
        $callback = $match['callback'];

        $next = static function () use ($resolver, $callback, $request): mixed {
            $args = $resolver->resolve($callback, $request->params());

            return $callback(...$args);
        };

        foreach (array_reverse($match['middlewares']) as $config) {
            $instance = self::makeMiddleware($config);
            $currentNext = $next;
            $next = static function () use ($instance, $request, $response, $currentNext): mixed {
                return self::invokeMiddleware($instance, $request, $response, $currentNext);
            };
        }

        return self::toResponse($next(), $response);
    }

    /**
     * Appelle un middleware, quel que soit son style: `__invoke()` (MiddlewareInterface).
     */
    public static function invokeMiddleware(
        object $instance,
        Request $request,
        Response $response,
        callable $next
    ): mixed {
        if (is_callable($instance)) {
            return $instance($request, $response, $next);
        }

        return $next();
    }

    // ------------------------------------------------------------------
    // Aides partagées
    // ------------------------------------------------------------------

    /**
     * Normalise le retour d'un handler en `Response`.
     *
     *  - `Response` : renvoyée telle quelle ;
     *  - `array` / `object` : encodé en JSON dans le corps ;
     *  - `'redirect:code:url'` : statut + en-tête `Location` ;
     *  - scalaire : corps de la réponse ;
     *  - `null` / `true` / `false` : réponse inchangée.
     */
    public static function toResponse(mixed $result, Response $response): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        // `true` / `false` / `null` : le handler n'a rien produit (ou a
        // interrompu la chaîne), la réponse en cours est conservée.
        if ($result === null || is_bool($result)) {
            return $response;
        }

        if (is_string($result) && str_starts_with($result, 'redirect:')) {
            $parts = explode(':', $result, 3);

            if (count($parts) === 3) {
                [, $status, $url] = $parts;
                $response->status = (int) $status;
                $response->headers['Location'] = $url;
                $response->body = $result;

                return $response;
            }
        }

        if (is_array($result) || is_object($result)) {
            $response->body = json_encode($result);

            return $response;
        }

        if (is_scalar($result)) {
            $response->body = (string) $result;
        }

        return $response;
    }

    /**
     * Normalise une déclaration de middleware en `['class' => ..., 'args' => [...]]`.
     *
     * Formes acceptées :
     *  - `MaClasse::class` ;
     *  - `['class' => MaClasse::class, 'args' => [...]]` ;
     *  - `[MaClasse::class, 'arg1', 'arg2']` (arguments positionnels) ;
     *  - `[MaClasse::class, ['clé' => 'valeur']]` (arguments nommés) ;
     *  - une instance de middleware.
     *
     * @return array{class: string, args: array, instance?: object}
     */
    public static function normalizeMiddleware(mixed $config): array
    {
        if (is_string($config)) {
            return ['class' => $config, 'args' => []];
        }

        if (is_object($config)) {
            return ['class' => $config::class, 'args' => [], 'instance' => $config];
        }

        if (is_array($config)) {
            if (isset($config['class'])) {
                return [
                    'class' => (string) $config['class'],
                    'args' => $config['args'] ?? $config['options'] ?? [],
                ] + (isset($config['instance']) ? ['instance' => $config['instance']] : []);
            }

            $class = $config[0] ?? null;

            if (!is_string($class)) {
                throw new \InvalidArgumentException(
                    'Un middleware doit être une classe, une instance ou un tableau [classe, ...arguments].'
                );
            }

            $rest = array_slice($config, 1);
            $args = count($rest) === 1 && is_array($rest[0]) ? $rest[0] : $rest;

            return ['class' => $class, 'args' => $args];
        }

        throw new \InvalidArgumentException(
            'Middleware invalide : ' . get_debug_type($config)
        );
    }

    /**
     * Instancie (ou récupère) l'instance d'un middleware normalisé.
     */
    public static function makeMiddleware(array $config): object
    {
        if (isset($config['instance']) && is_object($config['instance'])) {
            return $config['instance'];
        }

        $class = $config['class'];

        if (!class_exists($class)) {
            throw new \InvalidArgumentException("La classe de middleware ['$class'] n'existe pas.");
        }

        return new $class(...($config['args'] ?? []));
    }

    /**
     * Normalise un chemin : pas de slash final, racine = chaîne vide.
     */
    public function normalizePath(string $path): string
    {
        return rtrim($path, '/');
    }

    private function hasParameters(string $route): bool
    {
        return preg_match('/\{(\w+)(?::(\w+))?\}/', $route) === 1;
    }

    /**
     * Construit le motif regex d'une route paramétrée (`{id}`, `{chemin:path}`).
     */
    private function buildPattern(string $route): string
    {
        $escaped = preg_quote($route, '/');

        $pattern = preg_replace_callback(
            '/\\\\\{(\w+)(?:\\\\:(\w+))?\\\\\}/',
            static function (array $matches): string {
                $name = $matches[1];
                $type = $matches[2] ?? null;
                $capture = ($type === 'path') ? '.*' : '[^\/]+';

                return '(?P<' . $name . '>' . $capture . ')';
            },
            $escaped
        );

        return '/^' . $pattern . '$/';
    }
}
