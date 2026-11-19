<?php

namespace Eorbahapi;

use Eorbahapi\Cache\CacheManager;
use Eorbahapi\Dependency\DependencyResolver;
use Eorbahapi\Exceptions\EorbahAPIError;
use Eorbahapi\Openapi\Docs;
use Eorbahapi\Openapi\OpenApiBuilder;
use Eorbahapi\Responses\FileResponse;
use Eorbahapi\Responses\HTMLResponse;
use Eorbahapi\Responses\JSONResponse;
use Eorbahapi\Routing\Attributes\Middleware as MiddlewareAttribute;
use Eorbahapi\Routing\Route;
use Eorbahapi\Websockets\WebSocketRouter;
use Eorbahapi\Adapter\Http\RequestAdapter;
use Eorbahapi\Adapter\Http\ResponseAdapter;
use Eorbahapi\Contracts\ESGIAppInterface;
use Eorbahapi\Contracts\RequestInterface;
use Eorbahapi\Contracts\ResponseInterface;
use Eorbahapi\Request;
use Eorbahapi\Response;

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
class EorbahAPI implements ESGIAppInterface
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

    /** Classe qui rend les retours `array` / `object` (`JSONResponse` par défaut). */
    public string $response_class;

    private DependencyResolver $resolver;

    /** Façade de cache (désactivée par défaut). */
    private CacheManager $cacheManager;

    /** @var array<string, array<string, mixed>|null> Routes déjà appariées (cache mémoire). */
    private array $routeCache = [];

    /** @var list<string> Routes de documentation, exclues du schéma OpenAPI. */
    private array $docsRoutes = [];

    /** Routes WebSocket, servies par `WebSocketServer` ou un adaptateur dédié. */
    private WebSocketRouter $websocketRouter;

    public function __construct(
        public string $title = 'EorbahAPI',
        public string $description = '',
        public bool $debug = false,
        ?string $prefix = '',
        public bool $cache = false,
        public ?string $cache_dir = null,
        public ?int $cache_ttl = null,
        public ?string $summary = null,
        public string $version = '0.1.0',
        public ?string $terms_of_service = null,
        public ?array $contact = null,
        public ?array $license_info = null,
        public ?string $openapi_url = '/openapi.json',
        public ?array $openapi_tags = null,
        public ?array $openapi_external_docs = null,
        string $default_response_class = JSONResponse::class,
        public ?string $docs_url = '/docs',
        public ?string $redoc_url = '/redoc',
        public ?string $swagger_ui_oauth2_redirect_url = '/docs/oauth2-redirect',
        public ?array $swagger_ui_init_oauth = null,
        public ?array $swagger_ui_parameters = null,
    ) {
        self::assertResponseClass($default_response_class);

        $this->response_class = $default_response_class;

        $this->request = new Request();
        $this->response = new Response();
        $this->router = new APIRouter($prefix);
        $this->websocketRouter = new WebSocketRouter();
        $this->resolver = new DependencyResolver($this->request, $this->response);
        $this->cacheManager = new CacheManager($cache, null, $cache_dir, $cache_ttl ?? 0);
        $this->registerDefaultExceptionHandlers();
        $this->registerDocumentationRoutes();
    }

    /**
     * Imlementation ESGI
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseAdapter|ResponseInterface
     */
    public function __invoke(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $concreteRequest = $request instanceof RequestAdapter
            ? $request->unwrap()
            : new Request(
                body: $request->getBody(),
                params: $request->getPathParams(),
                server: $request->getServerParams(),
                query: $request->getQueryParams(),
                headers: $request->getHeaders(),
            );

        $concreteResponse = $response instanceof ResponseAdapter
            ? $response->unwrap()
            : new Response($response->body(), $response->status(), $response->headers());

        $result = $this->handle($concreteRequest, $concreteResponse);

        $response->setStatus($result->status);
        $response->setBody($result->body);

        foreach ($result->headers as $name => $value) {
            $response->setHeader((string) $name, (string) $value);
        }

        // Les cookies posés par l'application sont reportés sur l'adaptateur
        // (Swoole et autres serveurs ne passent pas par `setcookie()`).
        if ($response instanceof ResponseAdapter && $result instanceof Response) {
            $target = $response->unwrap();

            if ($target !== $result && $result->cookies !== []) {
                $target->cookies = array_merge($target->cookies, $result->cookies);
            }
        }

        return $response;
    }

    /**
     * Enregistre les routes de documentation déclarées par le constructeur.
     *
     * Les options FastAPI-style (`openapi_url`, `docs_url`, `redoc_url`,
     * `swagger_ui_oauth2_redirect_url`) sont appliquées ici : `openapi_url: null`
     * désactive la spécification et, par conséquent, Swagger UI et ReDoc.
     */
    private function registerDocumentationRoutes(): void
    {
        if ($this->openapi_url === null) {
            return;
        }

        // `null` désactive la page ; `openapi_routes()` reçoit `false` pour ne
        // pas retomber sur ses valeurs par défaut.
        $docsUrl = $this->docs_url ?? false;
        $redocUrl = $this->redoc_url ?? false;

        $oauth2Redirect = $this->docs_url === null || $this->swagger_ui_oauth2_redirect_url === null
            ? false
            : $this->swagger_ui_oauth2_redirect_url;

        $this->openapi_routes(
            '',
            [],
            openapi_url: $this->openapi_url,
            docs_url: $docsUrl,
            redoc_url: $redocUrl,
            oauth2_redirect_url: $oauth2Redirect,
            swagger_options: $this->swaggerOptions(),
        );
    }

    /**
     * Métadonnées OpenAPI déduites du constructeur (`info`, `tags`, `externalDocs`).
     *
     * @return array<string, mixed>
     */
    public function openapi_options(): array
    {
        return array_filter([
            'title' => $this->title,
            'description' => $this->description,
            'version' => $this->version,
            'summary' => $this->summary,
            'terms_of_service' => $this->terms_of_service,
            'contact' => $this->contact,
            'license' => $this->license_info,
            'tags' => $this->openapi_tags,
            'external_docs' => $this->openapi_external_docs,
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * Options par défaut des pages Swagger UI.
     *
     * @return array<string, mixed>
     */
    private function swaggerOptions(): array
    {
        return array_filter([
            'swagger_ui_init_oauth' => $this->swagger_ui_init_oauth,
            'swagger_ui_parameters' => $this->swagger_ui_parameters,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * Change la classe qui rend les retours `array` / `object`.
     *
     *     $app->set_response_class(EnvelopeResponse::class);
     */
    public function set_response_class(string $class): self
    {
        self::assertResponseClass($class);
        $this->response_class = $class;

        return $this;
    }

    private static function assertResponseClass(string $class): void
    {
        if (!class_exists($class) || !is_a($class, Response::class, true)) {
            throw new EorbahAPIError(
                "La classe de réponse [$class] doit étendre " . Response::class . '.'
            );
        }
    }

    private function registerDefaultExceptionHandlers(): void
    {
        $handlers = new ExceptionHandlers($this->debug);
        $handlers->overrideExceptionHandlers($this);
    }

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

        $response = APIRouter::toResponse($result, $this->response, $this->response_class);

        // Une réponse fichier a besoin de la requête pour Range/If-None-Match.
        if ($response instanceof FileResponse) {
            $response->withRequest($this->request);
        }

        // Les middlewares positionnent leurs en-têtes sur la réponse partagée
        // ($this->response). Si le handler a produit un autre objet Response,
        // on y reporte les en-têtes manquants (CORS, cache...).
        if ($response !== $this->response) {
            foreach ($this->response->headers as $name => $value) {
                if (!array_key_exists($name, $response->headers)) {
                    $response->headers[$name] = $value;
                }
            }

            // Les cookies posés sur la réponse partagée (via l'injection de
            // `Response`) doivent survivre au rendu par la classe de réponse.
            if ($response->cookies !== [] || $this->response->cookies !== []) {
                $response->cookies = array_merge($this->response->cookies, $response->cookies);
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
        $match = $this->matchRoute($uri, $method);

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
     * Cherche la route correspondant à la requête, avec un cache mémoire
     * lorsque le cache de l'application est activé.
     *
     * Le cache évite de relancer les expressions régulières des routes
     * paramétrées à chaque requête d'un runner persistant. Il est borné pour
     * qu'une URL très variable (identifiants, slugs) ne fasse pas grossir la
     * mémoire indéfiniment.
     *
     * @return array{callback: callable, middlewares: array, params: array<string, string>}|null
     */
    private function matchRoute(string $uri, string $method): ?array
    {
        if (!$this->cacheManager->enabled()) {
            return $this->router->match($uri, $method);
        }

        $key = strtoupper($method) . ' ' . $uri;

        if (array_key_exists($key, $this->routeCache)) {
            return $this->routeCache[$key];
        }

        if (count($this->routeCache) >= 1024) {
            $this->routeCache = [];
        }

        return $this->routeCache[$key] = $this->router->match($uri, $method);
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

            // Applications montées (StaticFiles, JsonRPC, BaseMountedApplication...) :
            // handle() remplit et retourne la réponse partagée.
            if (is_object($app) && method_exists($app, 'handle')) {
                $result = $app->handle($subRequest, $this->response);

                if ($result instanceof Response) {
                    return $result;
                }
            }

            // Repli historique : applications qui émettent elles-mêmes leur réponse.
            if (is_object($app) && method_exists($app, 'run')) {
                $app->run();

                return new Response();
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

    /**
     * Enregistre une route WebSocket.
     *
     *     $app->websocket('/ws/rooms/{room}', function (WebSocket $ws) {
     *         $ws->send('bienvenue dans ' . $ws->params()['room']);
     *     });
     *
     * Ces routes ne passent pas par le pipeline HTTP : elles sont servies par
     * `Eorbahapi\Websockets\WebSocketServer` (ou un adaptateur WebSocket).
     */
    public function websocket(string $route, callable $handler): self
    {
        $this->websocketRouter->add($route, $handler);

        return $this;
    }

    /**
     * Routes WebSocket enregistrées.
     */
    public function websocket_routes(): WebSocketRouter
    {
        return $this->websocketRouter;
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
        trigger_error("`include_routes` est deprecier il a été renommer `include_controller`");
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
    // Cache
    // ------------------------------------------------------------------

    /**
     * Façade de cache de l'application.
     *
     * Elle reste utilisable même lorsque le cache est désactivé : `remember()`
     * recalcule alors la valeur à chaque appel (`NullCache`), si bien que le
     * code applicatif n'a pas à connaître la configuration.
     *
     *     $spec = $app->cache_manager()->remember('spec', fn () => $app->openapi());
     */
    public function cache_manager(): CacheManager
    {
        return $this->cacheManager;
    }

    /**
     * Remplace la façade de cache, par exemple pour injecter un magasin
     * existant (Redis, `ArrayCache` en test…).
     *
     *     $app->set_cache_manager(new CacheManager(true, store: new ArrayCache()));
     */
    public function set_cache_manager(CacheManager $manager): self
    {
        $this->cacheManager = $manager;

        return $this;
    }

    // ------------------------------------------------------------------
    // Documentation OpenAPI
    // ------------------------------------------------------------------

    /**
     * Spécification OpenAPI 3.1 de l'application.
     *
     * Les routes, leurs paramètres (attributs `Eorbahapi\Params`) et leurs
     * modèles Svinx sont introspectés automatiquement. Lorsque
     * `EorbahAPI(cache: true)` est utilisé, le résultat est mis en cache fichier
     * et n'est recalculé qu'après modification des routes ou de leurs fichiers.
     *
     * @param array<string, mixed> $options Options du générateur (titre, version…).
     * @return array<string, mixed>
     */
    public function openapi(array $options = []): array
    {
        $options = array_replace($this->openapi_options(), $options);

        $builder = new OpenApiBuilder($this, $options);

        if (!$this->cacheManager->enabled()) {
            return $builder->build();
        }

        return $this->cacheManager->remember(
            'openapi-' . $builder->fingerprint(),
            static fn (): array => $builder->build()
        );
    }

    /**
     * Spécification OpenAPI sérialisée en JSON.
     *
     * @param array<string, mixed> $options Options du générateur.
     */
    public function openapi_json(array $options = [], bool $pretty = true): string
    {
        $json = (string) json_encode(
            $this->openapi($options),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            | ($pretty ? JSON_PRETTY_PRINT : 0)
            | JSON_THROW_ON_ERROR
        );

        return $pretty ? $json . "\n" : $json;
    }

    /**
     * Écrit la spécification OpenAPI dans un fichier `.json`.
     *
     *     $app->write_openapi('public/openapi.json');
     *
     * @param array<string, mixed> $options Options du générateur.
     * @return string Chemin du fichier écrit.
     */
    public function write_openapi(string $path = 'openapi.json', array $options = []): string
    {
        $json = $this->openapi_json($options);
        $directory = dirname($path);

        if ($directory !== '' && $directory !== '.' && !is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        if (@file_put_contents($path, $json) === false) {
            throw new EorbahAPIError("Impossible d'écrire le fichier OpenAPI [$path].");
        }

        return $path;
    }

    /**
     * Page Swagger UI, pour essayer les routes depuis le navigateur.
     *
     * @param array<string, mixed> $options `openapi_url`, `oauth2_redirect_url`,
     *                                      `title`, et les URL des fichiers CDN.
     */
    public function docs(array $options = []): HTMLResponse
    {
        $options = array_replace($this->swaggerOptions(), $options);

        $openapiUrl = (string) ($options['openapi_url'] ?? $this->openapi_url ?? '/openapi.json');
        $oauth2Url = $options['oauth2_redirect_url'] ?? $this->swagger_ui_oauth2_redirect_url;

        return new HTMLResponse(Docs::get_swagger_ui_html(
            $openapiUrl,
            $options['title'] ?? $this->title,
            $oauth2Url === null ? null : (string) $oauth2Url,
            $options
        ));
    }

    /**
     * Page ReDoc, pour lire la spécification confortablement.
     *
     * @param array<string, mixed> $options `openapi_url`, `title`, CDN.
     */
    public function redoc(array $options = []): HTMLResponse
    {
        $openapiUrl = (string) ($options['openapi_url'] ?? $this->openapi_url ?? '/openapi.json');

        return new HTMLResponse(Docs::get_redoc_html(
            $openapiUrl,
            $options['title'] ?? $this->title,
            $options
        ));
    }

    /**
     * Page de retour du flux OAuth2 de Swagger UI.
     */
    public function swagger_ui_oauth2_redirect_html(): HTMLResponse
    {
        return new HTMLResponse(Docs::get_swagger_ui_oauth2_redirect_html());
    }

    /**
     * Enregistre les routes de documentation.
     *
     * La voie principale reste le constructeur (`openapi_url`, `docs_url`,
     * `redoc_url`, `swagger_ui_oauth2_redirect_url`) : ces routes sont déjà
     * enregistrées à la création de l'application. Cette méthode sert au
     * placement avancé (préfixe commun, options propres à chaque route) ou
     * après avoir désactivé la documentation automatique (`openapi_url: null`).
     *
     *     // Emplacements par défaut, sous un préfixe commun :
     *     $app->openapi_routes('/api');
     *     // /api/openapi.json, /api/docs, /api/docs/oauth2-redirect, /api/redoc
     *
     *     // Emplacements personnalisés (chemins relatifs au préfixe) :
     *     $app->openapi_routes(
     *         '/api',
     *         openapi_url: '/spec.json',
     *         docs_url: '/swagger',
     *         redoc_url: '/reference',
     *         oauth2_redirect_url: '/swagger/oauth2',
     *     );
     *     // /api/spec.json, /api/swagger, /api/swagger/oauth2, /api/reference
     *
     *     // Sans préfixe : chemins à la racine de l'application.
     *     $app->openapi_routes('', docs_url: '/documentation', redoc_url: '/reference');
     *
     * Chaque chemin accepte `false` pour ne pas être enregistré. Sans
     * spécification (`openapi_url: false`), les pages qui la lisent (Swagger UI,
     * ReDoc) ne sont pas enregistrées non plus. Le préfixe de l'application
     * (`new EorbahAPI(prefix: '/v1')`) est appliqué au final à toutes ces
     * routes. Elles n'apparaissent jamais dans la spécification générée.
     *
     * Chaque route accepte ses propres options, fusionnées au tableau `$options`
     * commun :
     *
     *     $app->openapi_routes(
     *         '/api',
     *         openapi_options: ['title' => 'Catalogue', 'version' => '2.4.0'],
     *         swagger_options: ['title' => 'Catalogue — essayer', 'swagger_js_url' => '…'],
     *         redoc_options: ['title' => 'Catalogue — lire', 'redoc_js_url' => '…'],
     *     );
     *
     * @param array<string, mixed> $options              Options communes aux trois routes.
     * @param string|false|null    $openapi_url          Chemin du JSON (défaut `/openapi.json`).
     * @param string|false|null    $docs_url             Chemin de Swagger UI (défaut `/docs`).
     * @param string|false|null    $redoc_url            Chemin de ReDoc (défaut `/redoc`).
     * @param string|false|null    $oauth2_redirect_url  Chemin du retour OAuth2
     *                                                   (défaut `<docs_url>/oauth2-redirect`).
     * @param array<string, mixed> $openapi_options      Options du générateur (`title`, `version`,
     *                                                   `description`, `servers`, `operations`, `components`…).
     * @param array<string, mixed> $swagger_options      Options de Swagger UI (`title`,
     *                                                   `swagger_js_url`, `swagger_css_url`,
     *                                                   `swagger_preset_url`, `favicon_url`…).
     * @param array<string, mixed> $redoc_options        Options de ReDoc (`title`, `redoc_js_url`,
     *                                                   `favicon_url`…).
     */
    public function openapi_routes(
        string $prefix = '',
        array $options = [],
        string|false|null $openapi_url = null,
        string|false|null $docs_url = null,
        string|false|null $redoc_url = null,
        string|false|null $oauth2_redirect_url = null,
        array $openapi_options = [],
        array $swagger_options = [],
        array $redoc_options = [],
    ): self {
        $base = trim($prefix, '/') === '' ? '' : '/' . trim($prefix, '/');

        $specRelative = self::documentation_path($openapi_url, '/openapi.json');
        $docsRelative = self::documentation_path($docs_url, '/docs');
        $redocRelative = self::documentation_path($redoc_url, '/redoc');

        $oauthRelative = self::documentation_path(
            $oauth2_redirect_url,
            ($docsRelative ?? '/docs') . '/oauth2-redirect'
        );

        // Sans page Swagger UI, le retour OAuth2 par défaut n'a plus de sens.
        if ($docsRelative === null && $oauth2_redirect_url === null) {
            $oauthRelative = null;
        }

        $appPrefix = $this->router->prefix ?? '';

        // Chemin enregistré sur le routeur (le préfixe de l'application y est
        // ajouté par `add_api_route()`).
        $routePath = function (?string $relative) use ($base): ?string {
            if ($relative === null) {
                return null;
            }

            return $this->router->normalizePath($base . $relative);
        };

        // Chemin public (avec préfixe d'application), utilisé dans les pages
        // de documentation et pour masquer ces routes du schéma OpenAPI.
        $publicPath = function (?string $relative) use ($appPrefix, $base): ?string {
            if ($relative === null) {
                return null;
            }

            return $this->router->normalizePath($appPrefix . $base . $relative);
        };

        $specRoute = $routePath($specRelative);
        $docsRoute = $routePath($docsRelative);
        $oauth2Route = $routePath($oauthRelative);
        $redocRoute = $routePath($redocRelative);

        $specUrl = $publicPath($specRelative);
        $oauth2Url = $publicPath($oauthRelative);

        // Sans spécification, les pages de documentation n'auraient rien à afficher.
        if ($specRoute === null || $specUrl === null) {
            return $this;
        }

        $builderOptions = array_replace($options, $openapi_options);
        $swaggerOptions = array_replace($options, $swagger_options);
        $redocOptions = array_replace($options, $redoc_options);

        $sharedTitle = $options['title'] ?? $this->title;
        $swaggerTitle = $swaggerOptions['title'] ?? $sharedTitle;
        $redocTitle = $redocOptions['title'] ?? $sharedTitle;

        $this->get($specRoute, fn (): JSONResponse => new JSONResponse($this->openapi($builderOptions)));

        if ($docsRoute !== null) {
            $this->get($docsRoute, function () use ($specUrl, $oauth2Url, $swaggerTitle, $swaggerOptions): HTMLResponse {
                return new HTMLResponse(Docs::get_swagger_ui_html($specUrl, $swaggerTitle, $oauth2Url, $swaggerOptions));
            });
        }

        if ($oauth2Route !== null) {
            $this->get(
                $oauth2Route,
                static fn (): HTMLResponse => new HTMLResponse(Docs::get_swagger_ui_oauth2_redirect_html())
            );
        }

        if ($redocRoute !== null) {
            $this->get($redocRoute, function () use ($specUrl, $redocTitle, $redocOptions): HTMLResponse {
                return new HTMLResponse(Docs::get_redoc_html($specUrl, $redocTitle, $redocOptions));
            });
        }

        $this->docsRoutes = array_values(array_unique([
            ...$this->docsRoutes,
            ...array_values(array_filter(
                [$specUrl, $publicPath($docsRelative), $oauth2Url, $publicPath($redocRelative)],
                static fn (?string $path): bool => $path !== null
            )),
        ]));

        return $this;
    }

    /**
     * Normalise un chemin de documentation.
     *
     * `false` désactive la route, `null` garde la valeur par défaut, une chaîne
     * devient un chemin commençant par `/` (le préfixe de `openapi_routes()`
     * puis celui de l'application sont ajoutés ensuite).
     */
    private static function documentation_path(string|false|null $value, string $default): ?string
    {
        if ($value === false) {
            return null;
        }

        $path = '/' . ltrim($value ?? $default, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /**
     * Routes de documentation enregistrées (exclues du schéma OpenAPI).
     *
     * @return list<string>
     */
    public function documentation_routes(): array
    {
        return $this->docsRoutes;
    }

    /**
     * Sous-applications montées : [préfixe => application].
     *
     * @return array<string, mixed>
     */
    public function mounted_apps(): array
    {
        return $this->mountedApps;
    }



    public function disable(string $name)
    {
        switch ($name) {
            case 'headers':
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
