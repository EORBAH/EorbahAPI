<?php

namespace Eorbah545\Eorbahapi;

use EorBah545\Eorbahapi\Request;
use EorBah545\Eorbahapi\Response;

class EorbahAPI {
    private string $title;
    private array $routes = [];
    private array $Middlewares = [];
    private array $dynamicRoutes = [];

    private string $currentRoute = '';
    private string $currentMethod = '';

    public $request;
    public $response;

    /**
     * commentaires jsdoc
     */
    public function __construct(string $title = "EorbahAPI application") {
        $this->title = $title;
        $this->request = new Request();
        $this->response = new Response();
    }

    /**
     * commentaires jsdoc
     */
    public function get($route, $callback) {
        $this->currentRoute = $this->normalizeRoute($route);
        $this->currentMethod = 'GET';
        $this->registerRoute('GET', $route, $callback);
        return $this;
    }
    
    /**
     * commentaires jsdoc
     */
    public function post($route, $callback) {
        $this->currentRoute = $this->normalizeRoute($route);
        $this->currentMethod = 'POST';
        $this->registerRoute('POST', $route, $callback);
        return $this;
    }
    /**
     * commentaires jsdoc
     */
    public function put($route, $callback) {
        $this->currentRoute = $this->normalizeRoute($route);
        $this->currentMethod = 'PUT';
        $this->registerRoute('PUT', $route, $callback);
        return $this;
    }
    /**
     * commentaires jsdoc
     */
    public function delete($route, $callback) {
        $this->currentRoute = $this->normalizeRoute($route);
        $this->currentMethod = 'DELETE';
        $this->registerRoute('DELETE', $route, $callback);
        return $this;
    }

    /**
     * commentaires jsdoc
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

    /**
     * Midlewarres functions
     */
     /* ---------------------------------------------------*/
     /**
     * Ajoute un middleware global à l'application
     * Compatible avec EorbahAPI : add_middleware(CORSMiddleware::class, $options)
     * 
     * @param string $middlewareClass Classe du middleware (ex: CORSMiddleware::class)
     * @param array $options Options pour le middleware (allow_origins, etc.)
     * @return self
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
     * Usage: $app->get('/route', callback)->middleware([AuthMiddleware::class, 'admin'])
     * 
     * @param array $middlewareConfig [MiddlewareClass, ...options]
     * @return self
     */
    public function middleware($middlewareConfig) {
        if (empty($this->currentRoute) || empty($this->currentMethod)) {
            throw new \Exception("Vous devez définir une route avant d'ajouter un middleware");
        }

        $routeKey = $this->currentMethod . ':' . $this->currentRoute;

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
     * jsdoc comments
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
     * Applique les middlewares spécifiques à une route
     */
    private function applyRouteMiddlewares($middlewares, $segments) {
        foreach ($middlewares as $mwConfig) {
            $middlewareClass = $mwConfig['class'];
            $options = $mwConfig['options'];

            $middlewareInstance = new $middlewareClass(...$options);

            if (method_exists($middlewareInstance, 'process')) {
                $middlewareInstance->process($this->req, $this->res, function () use ($segments) {
                    return true;
                });
            }
        }
    }

    /**
     * Exécute la chaîne de middlewares globaux
     */
    private function executeMiddlewares() {
        foreach ($this->globalMiddlewares as &$mwConfig) {
            $middlewareClass = $mwConfig['class'];
            $options = $mwConfig['options'];

            if ($mwConfig['instance'] === null) {
                $mwConfig['instance'] = new $middlewareClass(...$options);
            }

            $middlewareInstance = $mwConfig['instance'];

            if (method_exists($middlewareInstance, '__invoke')) {
                $next = function () {
                    return true;
                };
                $middlewareInstance($this->req, $next);
            } elseif (method_exists($middlewareInstance, 'handle')) {
                $middlewareInstance->handle($this->req, $this->res);
            } elseif (method_exists($middlewareInstance, 'process')) {
                $middlewareInstance->process($this->req, $this->res, function () {
                    return true;
                });
            }
        }
    }
    /* --------------------- end --------------------------*/

    /**
     * La fonction mount de FastAPI sert à intégrer une application complète et indépendante 
     * (ou un gestionnaire de fichiers statiques) dans une application principale, en lui dédiant un chemin spécifique
     */
    public function mount() {}
    
    /*--------------- Includion de routes  ---------------*/
    public function IncludeRoute(string $RouteClass, array $option = []): void  {
        if (!class_exists($RouteClass)) {
            throw new InvalidArgumentException("La classe route '$RouteClass' n'existe pas.");
        }

        $RouteInstance = new $RouteClass();
        if (!isset($RouteInstance->config) || !is_array($RouteInstance->config)) {
            throw new RuntimeException("La classe '$RouteClass' doit avoir une propriété 'config' (array) contenant 'method' et 'route'.");
        }

        $config = $RouteInstance->config;
        $method = strtoupper($config['method'] ?? '');
        $route = $config['route'] ?? '';

        if (empty($method) || empty($route)) {
            throw new RuntimeException("La configuration de la route doit définir 'method' et 'route'.");
        }

        if (!method_exists($RouteInstance, '__invoke')) {
            throw new RuntimeException("La classe route '$RouteClass' doit implémenter la méthode __invoke().");
        }

        $callable = $RouteInstance;

        switch ($method) {
            case 'GET':
                $this->get($route, $callable, $option);
                break;
            case 'POST':
                $this->post($route, $callable, $option);
                break;
            case 'PUT':
                $this->put($route, $callable, $option);
                break;
            case 'DELETE':
                $this->delete($route, $callable, $option);
                break;
            default:
                throw new InvalidArgumentException("Méthode HTTP non supportée : $method");
        }
    }

    public function IncludeRoutes(string $RouteClass, array $option = []): void  {
        if (!class_exists($RouteClass)) {
            throw new InvalidArgumentException("La classe route '$RouteClass' n'existe pas.");
        }

         $RouteInstance = new $RouteClass();

        if (!method_exists($RouteInstance, '__invoke')) {
            throw new RuntimeException("La classe route '$RouteClass' doit implémenter la méthode __invoke().");
        }

        $callable = $RouteInstance;
        $callable($this);
    }

    private function normalizeRoute($route) {
        return rtrim($route, '/');
    }

    private function matchDynamicRoute($method, $uri) {
        if (!isset($this->dynamicRoutes[$method])) {
            return false;
        }

        foreach ($this->dynamicRoutes[$method] as $routeConfig) {
            $route = $routeConfig['route'];
            $matchType = $routeConfig['matchType'];
            $callback = $routeConfig['callback'];

            $pattern = $this->buildPattern($route);

            if (preg_match($pattern, $uri, $matches)) {
                $segments = $this->extractSegments($matches, $matchType);

                // Appliquer les middlewares spécifiques à la route
                if (isset($routeConfig['middlewares'])) {
                    $this->applyRouteMiddlewares($routeConfig['middlewares'], $segments);
                }
                
                $this->request->params($segments);
                $callback($this->request, $this->response);
                return true;
            }
        }
        return false;
    }

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

    public function run($http = "404", $handler = null) {
        // execution des middleware
        $this->executeMiddlewares();

        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $this->normalizeRoute(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

        if (isset($this->routes[$method][$uri])) {
            $routeConfig = $this->routes[$method][$uri];
            if (is_array($routeConfig) && isset($routeConfig['callback'])) {
                // Appliquer les middlewares de la route
                if (isset($routeConfig['middlewares'])) {
                    $this->applyRouteMiddlewares($routeConfig['middlewares'], []);
                }
                $routeConfig['callback']($this->request, $this->response);
            } else {
                $routeConfig($this->request, $this->response);
            }
            return;
        }

        if($this->matchDynamicRoute($method, $uri)) return;

        if ($http === '404') {
            http_response_code(404);
            if (is_callable($handler)) {
                $handler($this->request, $this->response);
            }
            return;
        } else {
            $segment = explode('/', $uri);
            if (is_callable($handler)) {
                $this->request->params($segment);
                $handler($this->request, $this->response);
            }
            return;
        }
    }
}