<?php

namespace EorBah545\Eorbahapi;

class EorbahAPI {
    private string $title;
    private array $routes = [];
    private array $mountedApps = [];
    private array $dynamicRoutes = [];
    private array $globalMiddlewares = [];
    

    private string $currentRoute = '';
    private string $currentMethod = '';

    public $request;
    public $response;

    public function __construct(string $title = "EorbahAPI application") {
        $this->title = $title;
        $this->request = new Request();
        $this->response = new Response();
    }

    /**
     * Route GET
     */
    public function get($route, $callback) {
        $this->currentRoute = $this->normalizeRoute($route);
        $this->currentMethod = 'GET';
        $this->registerRoute('GET', $route, $callback);
        return $this;
    }
    
    /**
     * Route POST
     */
    public function post($route, $callback) {
        $this->currentRoute = $this->normalizeRoute($route);
        $this->currentMethod = 'POST';
        $this->registerRoute('POST', $route, $callback);
        return $this;
    }

    /**
     * Route PUT
     */
    public function put($route, $callback) {
        $this->currentRoute = $this->normalizeRoute($route);
        $this->currentMethod = 'PUT';
        $this->registerRoute('PUT', $route, $callback);
        return $this;
    }

    /**
     * Route DELETE
     */
    public function delete($route, $callback) {
        $this->currentRoute = $this->normalizeRoute($route);
        $this->currentMethod = 'DELETE';
        $this->registerRoute('DELETE', $route, $callback);
        return $this;
    }

    /**
     * Enregistrement d'une route
     */
    private function registerRoute($method, $route, $callback) {
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

    /* -------------------- MIDDLEWARES -------------------- */

    /**
     * Ajoute un middleware global
     */
    public function addMiddleware($middlewareClass, $options = []) {
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
    public function middleware($middlewareConfig) {
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
    private function addDynamicRouteMiddleware($method, $route, $middlewareConfig) {
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
    private function applyRouteMiddlewares($middlewares, $segments, $routeCallback) {
        // Dernier maillon : la route elle-même
        $next = function() use ($routeCallback, $segments) {
            return call_user_func_array($routeCallback, $segments);
        };

        // Empiler les middlewares en sens inverse
        foreach (array_reverse($middlewares) as $mwConfig) {
            $middlewareInstance = new $mwConfig['class'](...$mwConfig['options']);
            $currentNext = $next;
            $next = function() use ($middlewareInstance, $currentNext) {
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
    private function  executeMiddlewares($routingCallback) {
        // Dernier maillon : le routage
        $next = $routingCallback;

        // Empiler les middlewares globaux en sens inverse
        foreach (array_reverse($this->globalMiddlewares) as &$mwConfig) {
            if ($mwConfig['instance'] === null) {
                $mwConfig['instance'] = new $mwConfig['class'](...$mwConfig['options']);
            }
            $middlewareInstance = $mwConfig['instance'];
            $currentNext = $next;
            $next = function() use ($middlewareInstance, $currentNext) {
                // Support des différentes interfaces
                if (method_exists($middlewareInstance, 'process')) {
                    $result = $middlewareInstance->process($this->request, $this->response, $currentNext);
                    if ($result === false) return false;
                    return $result;
                } elseif (method_exists($middlewareInstance, '__invoke')) {
                    $result = $middlewareInstance($this->request, $currentNext);
                    if ($result === false) return false;
                    return $result;
                } elseif (method_exists($middlewareInstance, 'handle')) {
                    $result = $middlewareInstance->handle($this->request, $this->response, $currentNext);
                    if ($result === false) return false;
                    return $result;
                }
                return $currentNext();
            };
        }

        return $next();
    }

    /* -------------------- FIN MIDDLEWARES -------------------- */

    /**
     * Mount (à implémenter)
     */
    public function mount() {}

    /**
     * Inclut une route (classe avec config)
     */
    public function IncludeRoute(string $RouteClass, array $option = []): void {
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
    public function IncludeRoutes(string $RouteClass, array $option = []): void {
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
    private function normalizeRoute($route) {
        return rtrim($route, '/');
    }

    /**
     * Cherche une correspondance avec une route dynamique
     */
    private function matchDynamicRoute($method, $uri) {
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

                // Construction du callback final (la route)
                $routeCallback = function() use ($callback) {
                    return $callback($this->request, $this->response);
                };

                // Exécution des middlewares de la route (s'il y en a)
                if (!empty($middlewares)) {
                    $result = $this->applyRouteMiddlewares($middlewares, [$this->request, $this->response], $routeCallback);
                    if ($result === false) {
                        return true; // Interruption par un middleware, on ne fait rien de plus
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
    private function buildPattern($route) {
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
    private function extractSegments($matches, $matchType) {
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
     * Lance l'application
     */
    public function run($http = "404", $handler = null) {
        // Construction du callback de routage
        $routingCallback = function() use ($http, $handler) {
            $method = $_SERVER['REQUEST_METHOD'];
            $uri = $this->normalizeRoute(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

            // Route statique
            if (isset($this->routes[$method][$uri])) {
                $routeConfig = $this->routes[$method][$uri];
                $middlewares = [];
                $callback = $routeConfig;

                if (is_array($routeConfig) && isset($routeConfig['callback'])) {
                    $callback = $routeConfig['callback'];
                    $middlewares = $routeConfig['middlewares'] ?? [];
                }

                $routeCallback = function() use ($callback) {
                    return $callback($this->request, $this->response);
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

            // Route dynamique
            if ($this->matchDynamicRoute($method, $uri)) {
                return true;
            }

            // Gestion 404 ou autre
            if ($http === '404') {
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

        // Exécution des middlewares globaux puis du routage
        $this->executeMiddlewares($routingCallback);
    }
}