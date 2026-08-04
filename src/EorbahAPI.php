<?php

namespace EorBah545\Eorbahapi;

class EorbahAPI
{
    private string $title;
    private array $routes = [];
    private array $mountedApps = [];
    private array $dynamicRoutes = [];
    private array $globalMiddlewares = [];

    private array $exceptionHandlers = [];

    private string $currentRoute = '';
    private string $currentMethod = '';

    public $request;
    public $response;

    private DependencyResolver $resolver;

    public function __construct(string $title = "EorbahAPI application")
    {
        $this->title = $title;
        $this->request = new Request();
        $this->response = new Response();
        $this->resolver = new DependencyResolver($this->request, $this->response);
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
     * Route GET
     */
    public function get($route, $callback): static
    {
        $this->currentRoute = $this->normalizeRoute($route);
        $this->currentMethod = 'GET';
        $this->registerRoute('GET', $route, $callback);
        return $this;
    }

    /**
     * Route POST
     */
    public function post($route, $callback): static
    {
        $this->currentRoute = $this->normalizeRoute($route);
        $this->currentMethod = 'POST';
        $this->registerRoute('POST', $route, $callback);
        return $this;
    }

    /**
     * Route PUT
     */
    public function put($route, $callback): static
    {
        $this->currentRoute = $this->normalizeRoute($route);
        $this->currentMethod = 'PUT';
        $this->registerRoute('PUT', $route, $callback);
        return $this;
    }

    /**
     * Route DELETE
     */
    public function delete($route, $callback): static
    {
        $this->currentRoute = $this->normalizeRoute($route);
        $this->currentMethod = 'DELETE';
        $this->registerRoute('DELETE', $route, $callback);
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

        $this->currentRoute = '';
        $this->currentMethod = '';
    }

    /**
     * Ajoute un middleware global
     */
    public function addMiddleware($middlewareClass, $options = []): static
    {
        $this->globalMiddlewares[] = [
            'class' => $middlewareClass,
            'options' => $options,
            'instance' => null
        ];
        return $this;
    }

    /**
     * Ajoute un middleware à la dernière route définie
     */
    public function middleware($middlewareConfig): static
    {
        if (empty($this->currentRoute) || empty($this->currentMethod)) {
            throw new \Exception("Vous devez définir une route avant d'ajouter un middleware");
        }

        if (!isset($this->routes[$this->currentMethod][$this->currentRoute])) {
            $this->addDynamicRouteMiddleware($this->currentMethod, $this->currentRoute, $middlewareConfig);
        } else {
            if (!isset($this->routes[$this->currentMethod][$this->currentRoute]['middlewares'])) {
                $this->routes[$this->currentMethod][$this->currentRoute] = [
                    'callback' => $this->routes[$this->currentMethod][$this->currentRoute],
                    'middlewares' => []
                ];
            }
            $this->routes[$this->currentMethod][$this->currentRoute]['middlewares'][] = [
                'class' => $middlewareConfig[0],
                'options' => array_slice($middlewareConfig, 1)
            ];
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
     * @param array $segments Paramètres de l'URL
     * @param callable $routeCallback Callback final de la route
     * @return bool
     */
    private function applyRouteMiddlewares($middlewares, $segments, $routeCallback): mixed
    {
        $next = function () use ($routeCallback, $segments) {
            return call_user_func_array($routeCallback, $segments);
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
                    return $result;
                } elseif (method_exists($middlewareInstance, '__invoke')) {
                    $result = $middlewareInstance($this->request, $currentNext);
                    if ($result === false)
                        return false;
                    return $result;
                } elseif (method_exists($middlewareInstance, 'handle')) {
                    $result = $middlewareInstance->handle($this->request, $this->response, $currentNext);
                    if ($result === false)
                        return false;
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
     * Inclut une route (classe avec config)
     */
    public function IncludeRoute(string $RouteClass, array $option = []): void
    {
        if (!class_exists($RouteClass)) {
            throw new \InvalidArgumentException("La classe route '$RouteClass' n'existe pas.");
        }

        $RouteInstance = new $RouteClass();
        if (!isset($RouteInstance->config) || !is_array($RouteInstance->config)) {
            throw new \RuntimeException("La classe '$RouteClass' doit avoir une propriété 'config' (array) contenant 'method' et 'route'.");
        }

        $config = $RouteInstance->config;
        $method = strtoupper($config['method'] ?? '');
        $route = $config['route'] ?? '';

        if (empty($method) || empty($route)) {
            throw new \RuntimeException("La configuration de la route doit définir 'method' et 'route'.");
        }

        if (!method_exists($RouteInstance, '__invoke')) {
            throw new \RuntimeException("La classe route '$RouteClass' doit implémenter la méthode __invoke().");
        }

        $callable = $RouteInstance;

        switch ($method) {
            case 'GET':
                $this->get($route, $callable);
                break;
            case 'POST':
                $this->post($route, $callable);
                break;
            case 'PUT':
                $this->put($route, $callable);
                break;
            case 'DELETE':
                $this->delete($route, $callable);
                break;
            default:
                throw new \InvalidArgumentException("Méthode HTTP non supportée : $method");
        }
    }

    /**
     * Inclut un ensemble de routes (classe invocable)
     */
    public function IncludeRoutes(string $RouteClass, array $option = []): void
    {
        if (!class_exists($RouteClass)) {
            throw new \InvalidArgumentException("La classe route '$RouteClass' n'existe pas.");
        }

        $RouteInstance = new $RouteClass();

        if (!method_exists($RouteInstance, '__invoke')) {
            throw new \RuntimeException("La classe route '$RouteClass' doit implémenter la méthode __invoke().");
        }

        $callable = $RouteInstance;
        $callable($this);
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
                $segments = $this->extractSegments($matches, $matchType);
                $this->request->params($segments);

                $routeCallback = function () use ($callback) {
                    $args = $this->resolver->resolve($callback, $this->request->params());
                    return $callback(...$args);
                };

                if (!empty($middlewares)) {
                    $result = $this->applyRouteMiddlewares($middlewares, [$this->request, $this->response], $routeCallback);
                    if ($result === false) {
                        return true;
                    }
                } else {
                    $routeCallback();
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
    private function extractSegments($matches, $matchType): array
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
            if ($result instanceof Response) {
                $result->send();
            }
        } else {
            $this->response->status(500)->send('Internal Server Error');
        }
        exit;
    }

    /**
     * Summary of run
     * @param mixed $http_code
     * @param mixed $handler
     * @return void
     */
    public function run($http_code = "404", $handler = null): void {
        try {
            $routingCallback = function () use ($http_code, $handler) {
                $method = $_SERVER['REQUEST_METHOD'];
                $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                $uri = $this->normalizeRoute($request_uri);

                foreach ($this->mountedApps as $prefix => $app) {
                    if ($uri === $prefix || strpos($uri, $prefix . '/') === 0) {
                        $subUri = substr($uri, strlen($prefix));
                        $subUri = '/' . ltrim($subUri, '/');

                        $originalUri = $_SERVER['REQUEST_URI'];
                        $originalPathInfo = $_SERVER['PATH_INFO'] ?? null;
                        $originalScriptName = $_SERVER['SCRIPT_NAME'] ?? null;

                        if ($originalScriptName) {
                            $_SERVER['SCRIPT_NAME'] = $originalScriptName . $prefix;
                        }
                        $_SERVER['REQUEST_URI'] = $subUri;
                        $_SERVER['PATH_INFO'] = $subUri;

                        try {
                            if ($app instanceof self) {
                                $app->run($http_code, $handler);
                            } elseif (method_exists($app, 'run')) {
                                $app->run($http_code, $handler);
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
                    } else {
                        $routeCallback();
                    }
                    return true;
                }

                if ($this->matchDynamicRoute($method, $uri)) {
                    return true;
                }

                if ($http_code === '404') {
                    http_response_code(404);
                    if (is_callable($handler)) {
                        $handler($this->request, $this->response);
                    }
                    return false;
                } else {
                    $segment = explode('/', $uri);
                    if (is_callable($handler)) {
                        $this->request->params($segment);
                        $handler($this->request, $this->response);
                    }
                    return false;
                }
            };

            $this->executeMiddlewares($routingCallback);
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }
}