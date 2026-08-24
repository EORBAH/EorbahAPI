<?php

namespace Eorbahapi;

use Eorbahapi\Request;
use Eorbahapi\Response;
use Eorbahapi\Routing\Route;
use Eorbahapi\Responses\JSONResponse;
use Eorbahapi\Exceptions\EorbahAPIError;
use Eorbahapi\Dependency\DependencyResolver;

class EorbahAPI
{
    private array $routes = [];
    private array $mountedApps = [];
    private array $dynamicRoutes = [];
    private array $globalMiddlewares = [];

    private array $exceptionHandlers = [];

    private array $default_route = [];

    public Request $request;
    public Response $response;

    private DependencyResolver $resolver;

    public function __construct(
        public ?string $title = null, 
        public ?string $description = null, 
        public bool $debug = false)
    {
        $this->request = new Request();
        $this->response = new Response();
        $this->resolver = new DependencyResolver($this->request, $this->response);
        $this->registerDefaultExceptionHandlers();
    }

    private function registerDefaultExceptionHandlers(): void
    {
        $handlers = new ExceptionHandlers($this->debug);
        $handlers->overrideExceptionHandlers($this);
    }

    /** */
    public function disable($name)
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


    /**
     * Enregistre une route pour plusieurs méthodes HTTP
     *
     * @param string $route   Le chemin de la route (ex: "/users")
     * @param string|array $methods  Méthodes HTTP, soit un tableau ['GET','POST'], soit une chaîne séparée par des pipes "GET|POST"
     * @param callable $callback Le callback à exécuter lorsque la route est appelée
     * @return static
     */
    public function add_api_route(string $route, $methods, callable $callback, array $middlewares = []): void
    {
        $methods = is_array($methods) ? $methods : explode('|', $methods);
        foreach ($methods as $method) {
            $method = strtoupper(trim($method));
            $this->registerRoute($method, $route, $callback);

            foreach ($middlewares as $mwConfig) {
                if (is_string($mwConfig)) {
                    $mwConfig = [$mwConfig];
                }

                $this->middleware($mwConfig, currentRoute: $this->normalizeRoute($route), currentMethod: $method);
            }
        }
    }

    public function default_route(int $http_code = 404, ?callable $handler = null) {
        $this->default_route['http_code'] = $http_code;
        $this->default_route['handler'] = $handler;
    }

    /**
     * Charge un router definie par APIRouter
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

        $routes = $router->routes;

        $prefix = $this->normalizeRoute($prefix . $router->prefix);

        foreach ($routes as $method => $routesConfig) {

            foreach ($routesConfig as $route => $routeConfig) {
                $middlewares = $routeConfig['routeConfig'] ?? [];
                $callback = $routeConfig['callback'] ?? null;
                $updateRoute = $prefix . $route;
                $this->add_api_route($updateRoute, [$method], $callback, $middlewares);
            }
        }
    }

    /**
     * Charge toutes les routes définies par attribut Route dans une classe.
     * Les méthodes publiques avec #[Route] sont enregistrées.
     *
     * @param string $routes_class Nom complet de la classe (avec namespace)
     * @param array $args Arguments à passer au constructeur de la classe
     * @return self
     */
    public function include_routes(string $routes_class, ?string $prefix = null, array $args = []): self
    {
        if (!class_exists($routes_class)) {
            throw new EorbahAPIError("La classe ['$routes_class'] n'existe pas.");
        }

        // Instanciation de la classe
        $reflectionClass = new \ReflectionClass($routes_class);
        $instance = $reflectionClass->newInstanceArgs($args);


        // Parcours des méthodes publiques
        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(Route::class);
            if (empty($attributes)) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();

            if ($prefix !== null) {
                $route = $this->normalizeRoute($prefix);
                $route .= $attribute->route;
            } else {
                $route = $attribute->route;
            }

            $methods = $attribute->methods;
            $middlewares = $attribute->middlewares;

            $callback = [$instance, $method->getName()];

            foreach ($methods as $httpMethod) {
                $httpMethod = strtoupper(trim($httpMethod));

                $this->add_api_route($route, [$httpMethod], $callback, $middlewares);
            }
        }

        return $this;
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
     * Ajoute un middleware global
     */
    public function add_middleware($middlewareClass, $options = []): static
    {
        $this->globalMiddlewares[] = [
            'class' => $middlewareClass,
            'options' => $options,
            'instance' => null
        ];

        return $this;
    }

    /**
     * Enregistrement d'une route
     */
    private function registerRoute($method, $route, $callback): void
    {
        $route = $this->normalizeRoute($route);

        if (preg_match('/\{(\w+)(?::(\w+))?\}/', $route)) {
            $this->dynamicRoutes[$method][] = [
                'route' => $route,
                'callback' => $callback,
                'matchType' => 'parametrized'
            ];
        } else {
            $this->routes[$method][$route] = $callback;
        }
    }

    /**
     * Ajoute un middleware à la dernière route définie
     */
    private function middleware($middlewareConfig, string $currentRoute = '', string $currentMethod = ''): static
    {

        if (empty($currentMethod)) {
            throw new EorbahAPIError("Vous devez définir une route avant d'ajouter un middleware");
        }

        if (!isset($this->routes[$currentMethod][$currentRoute])) {
            $this->addDynamicRouteMiddleware($currentMethod, $currentRoute, $middlewareConfig);
        } else {
            $routeConfig = $this->routes[$currentMethod][$currentRoute];
            if (!is_array($routeConfig) || !isset($routeConfig['middlewares'])) {
                $routeConfig = [
                    'callback' => $routeConfig,
                    'middlewares' => []
                ];
            }

            $routeConfig['middlewares'][] = [
                'class' => $middlewareConfig[0],
                'options' => array_slice($middlewareConfig, 1)
            ];

            $this->routes[$currentMethod][$currentRoute] = $routeConfig;
        }

        return $this;
    }

    /**
     * Ajoute un middleware à une route dynamique
     */
    private function addDynamicRouteMiddleware($method, $route, $middlewareConfig): void
    {
        foreach ($this->dynamicRoutes[$method] as &$dynamicRoute) {
            if ($dynamicRoute['route'] === $route) {
                if (!isset($dynamicRoute['middlewares'])) {
                    $dynamicRoute['middlewares'] = [];
                }
                $dynamicRoute['middlewares'][] = [
                    'class' => $middlewareConfig[0],
                    'options' => array_slice($middlewareConfig, 1)
                ];

                break;
            }
        }
    }

    /**
     * Applique les middlewares spécifiques à une route (version chaînée)
     * Retourne true si la route a été exécutée, false si interrompue
     *
     * @param array $middlewares Liste des middlewares
     * @param array $paramsPath Paramètres de l'URL
     * @param callable $routeCallback Callback final de la route
     * @return bool
     */
    private function applyRouteMiddlewares($middlewares, $paramsPath, $routeCallback): mixed
    {
        $next = function () use ($routeCallback, $paramsPath) {
            return call_user_func_array($routeCallback, $paramsPath);
        };

        foreach (array_reverse($middlewares) as $mwConfig) {
            $middlewareInstance = new $mwConfig['class'](...$mwConfig['options']);
            $currentNext = $next;
            $next = function () use ($middlewareInstance, $currentNext) {
                $result = $middlewareInstance->process($this->request, $this->response, $currentNext);
                if ($result === false) {
                    return false;
                }
                return $result;
            };
        }

        return $next();
    }

    /**
     * Exécute la chaîne de middlewares globaux (version chaînée)
     * Retourne true si la chaîne est allée jusqu'au bout, false si interrompue
     *
     * @param callable $routingCallback Callback qui lance le routage
     * @return bool
     */
    private function executeMiddlewares($routingCallback): mixed
    {
        $next = $routingCallback;


        foreach (array_reverse($this->globalMiddlewares) as &$mwConfig) {
            if ($mwConfig['instance'] === null) {
                $mwConfig['instance'] = new $mwConfig['class'](...$mwConfig['options']);
            }
            $middlewareInstance = $mwConfig['instance'];
            $currentNext = $next;
            $next = function () use ($middlewareInstance, $currentNext) {
                if (method_exists($middlewareInstance, 'process')) {
                    $result = $middlewareInstance->process($this->request, $this->response, $currentNext);
                    if ($result === false)
                        return false;
                    if ($result !== null && $result !== true) {
                        $this->applyReturn(result: $result);
                    }
                    return $result;
                } elseif (method_exists($middlewareInstance, '__invoke')) {
                    $result = $middlewareInstance($this->request, $currentNext);
                    if ($result === false)
                        return false;
                    if ($result !== null && $result !== true) {
                        $this->applyReturn(result: $result);
                    }
                    return $result;
                } elseif (method_exists($middlewareInstance, 'handle')) {
                    $result = $middlewareInstance->handle($this->request, $this->response, $currentNext);
                    if ($result === false)
                        return false;
                    if ($result !== null && $result !== true) {
                        $this->applyReturn(result: $result);
                    }
                    return $result;
                }
                return $currentNext();
            };
        }

        return $next();
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
        $prefix = $this->normalizeRoute($prefix);

        // Injection automatique si la méthode existe
        if (is_object($app) && method_exists($app, 'setRequestResponse')) {
            $app->setRequestResponse($this->request, $this->response);
        }

        $this->mountedApps[$prefix] = $app;
        return $this;
    }

    /**
     * Permet d'injecter les objets Request et Response (utilisé par mount)
     */
    public function setRequestResponse($request, $response): void
    {
        $this->request = $request;
        $this->response = $response;
    }


    /**
     * Normalise une route (supprime le slash final)
     */
    private function normalizeRoute($route): string
    {
        return rtrim($route, '/');
    }

    private function matchDynamicRoute($method, $uri)
    {
        if (!isset($this->dynamicRoutes[$method])) {
            return false;
        }

        foreach ($this->dynamicRoutes[$method] as $routeConfig) {
            $route = $routeConfig['route'];
            $matchType = $routeConfig['matchType'];
            $callback = $routeConfig['callback'];
            $middlewares = $routeConfig['middlewares'] ?? [];

            $pattern = $this->buildPattern($route);

            if (preg_match($pattern, $uri, $matches)) {
                $paramsPath = $this->extractParamsPath($matches, $matchType);
                $this->request->params(path: $paramsPath);

                $routeCallback = function () use ($callback) {
                    $args = $this->resolver->resolve($callback, $this->request->params());
                    return $callback(...$args);
                };

                if (!empty($middlewares)) {
                    $result = $this->applyRouteMiddlewares($middlewares, [$this->request, $this->response], $routeCallback);
                    if ($result === false) {
                        return true;
                    }
                    $this->applyReturn(result: $result);
                } else {
                    $this->applyReturn($routeCallback);
                }
                return true;
            }
        }
        return false;
    }

    /**
     * Construit le motif regex pour une route paramétrée
     */
    private function buildPattern($route): string
    {
        $escapedRoute = preg_quote($route, '/');
        $pattern = preg_replace_callback(
            '/\\\\\{(\w+)(?:\\\\:(\w+))?\\\\\}/',
            function ($matches) {
                $name = $matches[1];
                $type = $matches[2] ?? null;
                $capture = ($type === 'path') ? '.*' : '[^\/]+';
                return '(?P<' . $name . '>' . $capture . ')';
            },
            $escapedRoute
        );
        return "/^" . $pattern . "$/";
    }

    /**
     * Extrait les paramètres d'une correspondance
     */
    private function extractParamsPath($matches, $matchType): array
    {
        if ($matchType === 'parametrized') {
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }
            return $params;
        }
        return [];
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
     * Summary of handleException
     * @param \Throwable $e
     * @return never
     */
    public function handleException(\Throwable $e): void
    {
        $class = get_class($e);
        $handler = $this->exceptionHandlers[$class] ?? null;

        if (!$handler) {
            foreach ($this->exceptionHandlers as $exceptionType => $h) {
                if ($e instanceof $exceptionType) {
                    $handler = $h;
                    break;
                }
            }
        }

        if ($handler) {
            $result = $handler($e, $this->request, $this->response);
            if (is_array($result) || is_object($result)) {
                echo '';
            } elseif ($result !== null) {
                $this->response->body = $result;
                $this->response->send();
            }
        } else {
            $res = new JSONResponse(['error' => true, 'status' => 500, 'message' => 'Internal Server Error'], 500, ['Content-Type' => 'application/json; charset=UTF-8']);
            $res->send();
        }
        exit;
    }

    
    public function run(): void
    {
        $defaultHttpCode = $this->default_route['http_code'] ?? 404;
        $defaultRouteHandler = $this->default_route['handler'] ?? null;

        try {
            $routingCallback = function () use ($defaultHttpCode, $defaultRouteHandler) {
                $uri = $this->request->path();
                $method = $this->request->method();

                foreach ($this->mountedApps as $prefix => $app) {
                    if ($uri === $prefix || strpos($uri, $prefix . '/') === 0) {
                        $subUri = substr($uri, strlen($prefix));
                        $subUri = '/' . ltrim($subUri, '/');

                        $originalUri = $this->request->uri();
                        $originalPathInfo = $this->request->pathInfo() ?? null;
                        $originalScriptName = $_SERVER['SCRIPT_NAME'] ?? null;

                        if ($originalScriptName) {
                            $_SERVER['SCRIPT_NAME'] = $originalScriptName . $prefix;
                        }
                        $_SERVER['REQUEST_URI'] = $subUri;
                        $_SERVER['PATH_INFO'] = $subUri;

                        try {
                            if ($app instanceof self) {
                                $app->run();
                            } elseif (method_exists($app, 'run')) {
                                $app->run();
                            } elseif (method_exists($app, 'handle')) {
                                $app->handle($this->request, $this->response);
                            } elseif (is_callable($app)) {
                                $app($this->request, $this->response);
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

                        return true;
                    }
                }


                if (isset($this->routes[$method][$uri])) {
                    $routeConfig = $this->routes[$method][$uri];
                    $middlewares = [];
                    $callback = $routeConfig;

                    if (is_array($routeConfig) && isset($routeConfig['callback'])) {
                        $callback = $routeConfig['callback'];
                        $middlewares = $routeConfig['middlewares'] ?? [];
                    }

                    $routeCallback = function () use ($callback) {
                        $args = $this->resolver->resolve($callback, $this->request->params());
                        return $callback(...$args);
                    };

                    if (!empty($middlewares)) {
                        $result = $this->applyRouteMiddlewares($middlewares, [$this->request, $this->response], $routeCallback);
                        if ($result === false) {
                            return false;
                        }
                        $this->applyReturn(result: $result);
                    } else {
                        $this->applyReturn($routeCallback);
                    }
                    return true;
                }

                if ($this->matchDynamicRoute($method, $uri)) {
                    return true;
                }

                if (is_callable($defaultRouteHandler)) {
                    $this->response->status = $defaultHttpCode;
                    $defaultRouteCallback = function () use ($defaultRouteHandler) {
                        $args = $this->resolver->resolve($defaultRouteHandler, $this->request->params());
                        return $defaultRouteHandler(...$args);
                    };
                    $this->applyReturn($defaultRouteCallback);
                    return false;
                }

                return false;
            };

            $this->executeMiddlewares($routingCallback);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    private function applyReturn($routeCallback = null, $result = null)
    {
        if ($result === null && $routeCallback !== null) {
            $result = call_user_func_array($routeCallback, [$this->response]);
        }

        if ($result instanceof Response) {
            $result->send();
            return;
        }

        if (is_string($result) && str_starts_with($result, 'redirect:')) {
            [$prefix, $statusCode, $url] = explode(':', $result, 3);
            $this->response->status = (int) $statusCode;
            $this->response->headers['Location'] = $url;
            $this->response->body = $result;
            $this->response->send();
            return;
        }

        if (is_array($result) || is_object($result)) {
            $this->response->body = json_encode($result);
        } elseif ($result !== null) {
           $this->response->body = $result;
        }

        $this->response->send();
    }
}