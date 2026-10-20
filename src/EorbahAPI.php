<?php

namespace Eorbahapi;

use Eorbahapi\Dependency\DependencyResolver;
use Eorbahapi\Exceptions\EorbahAPIError;
use Eorbahapi\Responses\JSONResponse;
use Eorbahapi\Routing\Attributes\Middleware as MiddlewareAttribute;
use Eorbahapi\Routing\Route;

/**
 * Application HTTP.
 *
 * Le cycle de vie suit le modèle du core (`Application::handle()` &
 * `Application::run()`) :
 *
 *  - `handle(Request $request, ?Response $response = null): Response` construit
 *    la réponse sans rien envoyer : c'est le point d'entrée des runners
 *    personnalisés et du `TestClient` ;
 *  - `run()` branche la requête réelle (superglobales) et envoie la réponse.
 *
 *     $app = new EorbahAPI();
 *     $app->get('/users/{id}', fn (#[Path] int $id) => ['id' => $id]);
 *
 *     $app->run();                                   // production
 *     $response = $app->handle($customRequest);      // runner personnalisé
 */
class EorbahAPI
{
    /** Routeur des routes de l'application. */
    public APIRouter $router;

    /** @var array<string, mixed> Sous-applications montées : [préfixe => app] */
    private array $mountedApps = [];

    /** @var list<array{class: string, args: array, instance?: object}> Middlewares globaux. */
    private array $globalMiddlewares = [];

    private array $exceptionHandlers = [];

    private array $default_route = [];

    public Request $request;
    public Response $response;

    private DependencyResolver $resolver;

    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public bool $debug = false,
        ?string $prefix = '',
    ) {
        $this->request = new Request();
        $this->response = new Response();
        $this->router = new APIRouter($prefix);
        $this->resolver = new DependencyResolver($this->request, $this->response);
        $this->registerDefaultExceptionHandlers();
    }

    private function registerDefaultExceptionHandlers(): void
    {
        $handlers = new ExceptionHandlers($this->debug);
        $handlers->overrideExceptionHandlers($this);
    }

    // ------------------------------------------------------------------
    // Cycle de vie
    // ------------------------------------------------------------------

    /**
     * Traite une requête et retourne la réponse, sans l'envoyer.
     *
     * C'est le point d'entrée des runners personnalisés : on peut injecter sa
     * propre requête (et éventuellement sa propre réponse) et récupérer
     * l'objet `Response` fabriqué par l'application.
     */
    public function handle(Request $request, ?Response $response = null): Response
    {
        $this->setRequestResponse($request, $response ?? new Response());

        try {
            $result = $this->executeMiddlewares(fn (): mixed => $this->dispatch());
        } catch (\Throwable $e) {
            return $this->handleExceptionAsResponse($e);
        }

        $response = APIRouter::toResponse($result, $this->response);

        // Les middlewares positionnent leurs en-têtes sur la réponse partagée
        // ($this->response). Si le handler a produit un autre objet Response,
        // on y reporte les en-têtes manquants (CORS, cache...).
        if ($response !== $this->response) {
            foreach ($this->response->headers as $name => $value) {
                if (!array_key_exists($name, $response->headers)) {
                    $response->headers[$name] = $value;
                }
            }
        }

        return $response;
    }

    /**
     * Point d'entrée HTTP classique : traite la requête courante et l'envoie.
     *
     * Une requête peut être fournie (runner personnalisé) ; par défaut c'est
     * celle construite depuis les superglobales.
     */
    public function run(?Request $request = null): void
    {
        $this->handle($request ?? $this->request, $this->response)->send();
    }

    /**
     * Construit la réponse de la requête (routes, sous-applications, 404).
     */
    private function dispatch(): mixed
    {
        $uri = $this->request->path();
        $method = $this->request->method();

        // 1. Sous-application montée ? Le préfixe est retiré de l'URI.
        foreach ($this->mountedApps as $prefix => $app) {
            $prefix = (string) $prefix;

            if ($uri === $prefix || str_starts_with($uri, $prefix . '/')) {
                return $this->dispatchToMountedApp($prefix, $app, $uri);
            }
        }

        // 2. Route de l'application.
        $match = $this->router->match($uri, $method);

        if ($match !== null) {
            $this->request->params(path: $match['params']);

            return $this->runRoute($match);
        }

        // 3. Route par défaut définie par default_route().
        $defaultHandler = $this->default_route['handler'] ?? null;

        if (is_callable($defaultHandler)) {
            $this->response->status = (int) ($this->default_route['http_code'] ?? 404);
            $args = $this->resolver->resolve($defaultHandler, $this->request->params());

            return $defaultHandler(...$args);
        }

        if (isset($this->default_route['http_code'])) {
            $this->response->status = (int) $this->default_route['http_code'];

            return $this->response;
        }

        $this->response->status = 404;
        $this->response->body = 'Not Found';

        return $this->response;
    }

    /**
     * Exécute un handler de route en appliquant ses middlewares.
     */
    private function runRoute(array $match): mixed
    {
        $callback = $match['callback'];

        $next = function () use ($callback) {
            $args = $this->resolver->resolve($callback, $this->request->params());

            return $callback(...$args);
        };

        foreach (array_reverse($match['middlewares']) as $config) {
            $next = $this->wrapMiddleware($config, $next);
        }

        return $next();
    }

    /**
     * Délègue le traitement à une sous-application montée.
     */
    private function dispatchToMountedApp(string $prefix, mixed $app, string $uri): mixed
    {
        $subUri = '/' . ltrim(substr($uri, strlen($prefix)), '/');

        $originalUri = $this->request->uri();
        $originalPathInfo = $this->request->pathInfo();
        $originalScriptName = $_SERVER['SCRIPT_NAME'] ?? null;

        if ($originalScriptName !== null) {
            $_SERVER['SCRIPT_NAME'] = $originalScriptName . $prefix;
        }
        $_SERVER['REQUEST_URI'] = $subUri;
        $_SERVER['PATH_INFO'] = $subUri;

        try {
            $subRequest = $this->request->withUri($subUri);

            if ($app instanceof self) {
                return $app->handle($subRequest, $this->response);
            }

            // Applications montées historiques (BaseMountedApplication, JsonRPC...) :
            // elles émettent elles-mêmes leur réponse via run().
            if (is_object($app) && method_exists($app, 'run')) {
                $app->run();

                return new Response();
            }

            if (is_object($app) && method_exists($app, 'handle')) {
                $result = $app->handle($subRequest, $this->response);

                return $result instanceof Response ? $result : new Response();
            }

            if (is_callable($app)) {
                $result = $app($subRequest, $this->response);

                if ($result instanceof Response) {
                    return $result;
                }

                return new Response();
            }
        } finally {
            $_SERVER['REQUEST_URI'] = $originalUri;

            if ($originalPathInfo !== null) {
                $_SERVER['PATH_INFO'] = $originalPathInfo;
            } else {
                unset($_SERVER['PATH_INFO']);
            }

            if ($originalScriptName !== null) {
                $_SERVER['SCRIPT_NAME'] = $originalScriptName;
            } else {
                unset($_SERVER['SCRIPT_NAME']);
            }
        }

        return $this->response;
    }

    // ------------------------------------------------------------------
    // Middlewares
    // ------------------------------------------------------------------

    /**
     * Chaîne les middlewares globaux autour du traitement de la requête.
     */
    private function executeMiddlewares(callable $core): mixed
    {
        $next = $core;

        foreach (array_reverse(array_keys($this->globalMiddlewares)) as $index) {
            $config = $this->globalMiddlewares[$index];
            $instance = $this->globalMiddlewareInstance($index, $config);
            $currentNext = $next;

            $next = function () use ($instance, $currentNext): mixed {
                return $this->invokeMiddleware($instance, $currentNext);
            };
        }

        return $next();
    }

    /**
     * Enveloppe un successeur dans un middleware de route.
     */
    private function wrapMiddleware(array $config, callable $next): callable
    {
        $instance = APIRouter::makeMiddleware($config);

        return function () use ($instance, $next): mixed {
            return $this->invokeMiddleware($instance, $next);
        };
    }

    private function globalMiddlewareInstance(int $index, array $config): object
    {
        if (isset($config['instance']) && is_object($config['instance'])) {
            return $config['instance'];
        }

        return $this->globalMiddlewares[$index]['instance'] ??= APIRouter::makeMiddleware($config);
    }

    /**
     * Appelle un middleware, quel que soit son style : `process()`,
     * `__invoke()` (MiddlewareInterface) ou `handle()`.
     */
    private function invokeMiddleware(object $instance, callable $next): mixed
    {
        return APIRouter::invokeMiddleware($instance, $this->request, $this->response, $next);
    }

    /**
     * Ajoute un middleware global.
     *
     * @param object|string $middlewareClass Instance ou nom de classe.
     * @param array         $options         Arguments du constructeur
     *                                       (indexés ou nommés).
     */
    public function add_middleware(object|string $middlewareClass, $options = []): static
    {
        $config = APIRouter::normalizeMiddleware($middlewareClass);

        if (is_object($middlewareClass)) {
            $config['instance'] = $middlewareClass;
        } elseif ($options !== null && $options !== []) {
            $config['args'] = (array) $options;
        }

        $this->globalMiddlewares[] = $config;

        return $this;
    }

    // ------------------------------------------------------------------
    // Routage
    // ------------------------------------------------------------------

    public function get(string $route, callable $callback, array $middlewares = []): void
    {
        $this->router->get($route, $callback, $middlewares);
    }

    public function post(string $route, callable $callback, array $middlewares = []): void
    {
        $this->router->post($route, $callback, $middlewares);
    }

    public function put(string $route, callable $callback, array $middlewares = []): void
    {
        $this->router->put($route, $callback, $middlewares);
    }

    public function patch(string $route, callable $callback, array $middlewares = []): void
    {
        $this->router->patch($route, $callback, $middlewares);
    }

    public function delete(string $route, callable $callback, array $middlewares = []): void
    {
        $this->router->delete($route, $callback, $middlewares);
    }

    public function head(string $route, callable $callback, array $middlewares = []): void
    {
        $this->router->head($route, $callback, $middlewares);
    }

    public function options(string $route, callable $callback, array $middlewares = []): void
    {
        $this->router->options($route, $callback, $middlewares);
    }

    /**
     * Enregistre une route pour plusieurs méthodes HTTP
     *
     * @param string $route   Le chemin de la route (ex: "/users")
     * @param string|array $methods  Méthodes HTTP, soit un tableau ['GET','POST'], soit une chaîne séparée par des pipes "GET|POST"
     * @param callable $callback Le callback à exécuter lorsque la route est appelée
     * @return void
     */
    public function add_api_route(string $route, $methods, callable $callback, array $middlewares = []): void
    {
        $this->router->add_api_route($route, $methods, $callback, $middlewares);
    }

    public function default_route(int $http_code = 404, ?callable $handler = null) {
        $this->default_route['http_code'] = $http_code;
        $this->default_route['handler'] = $handler;
    }

    /**
     * Charge un router defini par APIRouter
     */
    public function include_router(
        $router = null,
        ?string $router_class = null,
        array $args = [],
        ?string $prefix = ''
    ) {
        if ($router_class !== null && $router == null) {
            if (!class_exists($router_class)) {
                throw new EorbahAPIError("La classe ['$router_class'] n'existe pas.");
            }

            $RouteInstance = new $router_class(...$args);

            if (!method_exists($RouteInstance, '__register_routes')) {
                throw new EorbahAPIError("La classe route ['$router_class'] doit implémenter la méthode __register_routes().");
            }

            $router = $RouteInstance->router;
        }

        if (!$router instanceof APIRouter) {
            throw new EorbahAPIError("include_router() attend une instance de [" . APIRouter::class . '].');
        }

        $prefix = $this->router->normalizePath((string) $prefix);

        foreach ($router->routes as $method => $routesConfig) {
            foreach ($routesConfig as $route => $routeConfig) {
                $this->router->add_api_route(
                    $prefix . $route,
                    [$method],
                    $routeConfig['callback'],
                    $routeConfig['middlewares'] ?? $routeConfig['routeConfig'] ?? []
                );
            }
        }

        foreach ($router->dynamicRoutes as $method => $routes) {
            foreach ($routes as $route) {
                $this->router->add_api_route(
                    $prefix . $route['route'],
                    [$method],
                    $route['callback'],
                    $route['middlewares'] ?? []
                );
            }
        }
    }

    /**
     * Charge toutes les routes déclarées par attributs d'un contrôleur.
     *
     * Les attributs reconnus sont [Route] et ses spécialisations ([Get], [Post],
     * [Put], [Patch], [Delete], [Head], [Options]), plus [Middleware] — au
     * niveau de la méthode comme de la classe.
     *
     *     $app->include_controller(new AuthRouter());
     *
     * @param object|string $controller Instance ou nom de classe du contrôleur.
     * @param string|null   $prefix     Préfixe ajouté à toutes ses routes.
     * @param array         $args       Arguments du constructeur si une classe est passée.
     */
    public function include_controller(object|string $controller, ?string $prefix = null, array $args = []): self
    {
        $class = is_object($controller) ? $controller::class : $controller;

        if (!class_exists($class)) {
            throw new EorbahAPIError("La classe ['$class'] n'existe pas.");
        }

        $reflection = new \ReflectionClass($class);
        $instance = is_object($controller) ? $controller : $reflection->newInstanceArgs($args);

        $prefix = $prefix !== null ? $this->router->normalizePath($prefix) : '';
        $classMiddlewares = $this->middlewaresFromAttributes($reflection->getAttributes(MiddlewareAttribute::class));

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $routeAttributes = $method->getAttributes(Route::class, \ReflectionAttribute::IS_INSTANCEOF);

            if ($routeAttributes === []) {
                continue;
            }

            $methodMiddlewares = $this->middlewaresFromAttributes($method->getAttributes(MiddlewareAttribute::class));
            $callback = [$instance, $method->getName()];

            foreach ($routeAttributes as $attribute) {
                /** @var Route $route */
                $route = $attribute->newInstance();

                $this->router->add_api_route(
                    $prefix . $route->route,
                    $route->methods,
                    $callback,
                    [...$classMiddlewares, ...$route->middlewares, ...$methodMiddlewares]
                );
            }
        }

        return $this;
    }

    /**
     * Charge toutes les routes définies par attribut Route dans une classe.
     *
     * @param string $routes_class Nom complet de la classe (avec namespace)
     * @param array $args Arguments à passer au constructeur de la classe
     * @return self
     */
    public function include_routes(string $routes_class, ?string $prefix = null, array $args = []): self
    {
        return $this->include_controller($routes_class, $prefix, $args);
    }

    /**
     * Inclut un ensemble de routes (classe invocable) definis dans _register_routes
     */
    public function register_routes(string $routeClass, array $args = []): void
    {
        if (!class_exists($routeClass)) {
            throw new EorbahAPIError("La classe route ['$routeClass'] n'existe pas.");
        }

        $RouteInstance = new $routeClass(...$args);

        if (!method_exists($RouteInstance, '__register_routes')) {
            throw new EorbahAPIError("La classe route ['$routeClass'] doit implémenter la méthode __register_routes().");
        }

        $RouteInstance->__register_routes($this);
    }

    /**
     * Normalise les attributs [Middleware] en configurations de middleware.
     *
     * @param list<\ReflectionAttribute> $attributes
     * @return list<array{class: string, args: array}>
     */
    private function middlewaresFromAttributes(array $attributes): array
    {
        $middlewares = [];

        foreach ($attributes as $attribute) {
            /** @var MiddlewareAttribute $middleware */
            $middleware = $attribute->newInstance();
            $middlewares[] = [
                'class' => $middleware->middleware,
                'args' => $middleware->args,
            ];
        }

        return $middlewares;
    }

    // ------------------------------------------------------------------
    // Montage de sous-applications
    // ------------------------------------------------------------------

    /**
     * Monte une sous-application sur un préfixe d'URL.
     * les requêtes commençant par $prefix
     * sont déléguées à $app après retrait du préfixe.
     *
     * @param string $prefix Le chemin de base (ex: "/admin")
     * @param mixed  $app    Instance d'EorbahAPI ou callable
     * @return self
     */
    public function mount(string $prefix, $app, $name = null): self
    {
        $prefix = $this->router->normalizePath($prefix);

        // Injection automatique si la méthode existe
        if (is_object($app) && method_exists($app, 'setRequestResponse')) {
            $app->setRequestResponse($this->request, $this->response);
        }

        $this->mountedApps[$prefix] = $app;

        return $this;
    }

    /**
     * Permet d'injecter les objets Request et Response (utilisé par mount ou
     * par un runner personnalisé).
     */
    public function setRequestResponse(Request $request, Response $response): void
    {
        $this->request = $request;
        $this->response = $response;
        $this->resolver->setRequestResponse($request, $response);
    }

    // ------------------------------------------------------------------
    // Conteneur et exceptions
    // ------------------------------------------------------------------

    /** */
    public function disable(string $name)
    {
        switch ($name) {
            case 'X-Powered-By':
                $this->response->removeHeader('X-Powered-By');
                break;

            default:
                break;
        }
    }

    /**
     * Enregistre une dépendance dans le conteneur (ex: connexion BDD)
     */
    public function register(string $id, mixed $value): self
    {
        $this->resolver->set($id, $value);
        return $this;
    }

    /**
     * Summary of setExceptionHandler
     * @param string $exceptionClass
     * @param callable $handler
     * @return EorbahAPI
     */
    public function setExceptionHandler(string $exceptionClass, callable $handler): self
    {
        $this->exceptionHandlers[$exceptionClass] = $handler;
        return $this;
    }

    /**
     * Envoie la réponse correspondant à une exception.
     */
    public function handleException(\Throwable $e): void
    {
        $this->handleExceptionAsResponse($e)->send();
    }

    /**
     * Construit la réponse d'une exception via le gestionnaire enregistré.
     */
    private function handleExceptionAsResponse(\Throwable $e): Response
    {
        $handler = $this->exceptionHandlers[get_class($e)] ?? null;

        if ($handler === null) {
            foreach ($this->exceptionHandlers as $exceptionType => $h) {
                if ($e instanceof $exceptionType) {
                    $handler = $h;
                    break;
                }
            }
        }

        if ($handler !== null) {
            $result = $handler($e, $this->request, $this->response);

            return APIRouter::toResponse($result, $this->response);
        }

        return new JSONResponse(
            ['error' => true, 'status' => 500, 'message' => 'Internal Server Error'],
            500,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }
}
