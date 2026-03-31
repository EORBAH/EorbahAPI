<?php

namespace EorBah545\Eorbahapi;

class EorbahAPI
{
    private array $routes = [];
    private array $dynamicRoutes = [];

    // Middlewares globaux
    private array $globalMiddlewares = [];

    public $res;
    public $req;

    // Dernière route enregistrée pour les middlewares spécifiques
    private string $currentRoute = '';
    private string $currentMethod = '';

    public function __construct()
    {
        $this->req = new Request();
        $this->res = new Response();
    }

    // utilisation pour static files

    public function addStaticFiles($route, $static, $type = null)
    {
        $this->get($route, function ($req) use ($static) {
            $params = $req->params();
            $requestedPath = implode('/', $params);
            if (!$static->serve($requestedPath)) {
                $this->res->status(404);
                //$this->res->send("404 Not Found");
                exit;
            }
        }, 'is-base');
    }

    // pas encore implementer

    public function mount()
    {
    }

    /**
     * Ajoute un middleware global à l'application
     * Compatible avec EorbahAPI : add_middleware(CORSMiddleware::class, $options)
     * 
     * @param string $middlewareClass Classe du middleware (ex: CORSMiddleware::class)
     * @param array $options Options pour le middleware (allow_origins, etc.)
     * @return self
     */
    public function addMiddleware($middlewareClass, $options = [])
    {
        // Enregistrer le middleware avec ses options
        $this->globalMiddlewares[] = [
            'class' => $middlewareClass,
            'options' => $options,
            'instance' => null // Instancié plus tard
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
    public function middleware($middlewareConfig)
    {
        if (empty($this->currentRoute) || empty($this->currentMethod)) {
            throw new \Exception("Vous devez définir une route avant d'ajouter un middleware");
        }

        $routeKey = $this->currentMethod . ':' . $this->currentRoute;

        if (!isset($this->routes[$this->currentMethod][$this->currentRoute])) {
            // Pour les routes dynamiques, stocker différemment
            $this->addDynamicRouteMiddleware($this->currentMethod, $this->currentRoute, $middlewareConfig);
        } else {
            // Pour les routes statiques
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

    private function addDynamicRouteMiddleware($method, $route, $middlewareConfig)
    {
        // Chercher dans les routes dynamiques
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

    public function get($route, $callback, $matchType = null)
    {
        $this->currentRoute = $this->normalizeRoute($route);
        $this->currentMethod = 'GET';
        $this->registerRoute('GET', $route, $callback, $matchType);
        return $this;
    }

    public function post($route, $callback, $matchType = null)
    {
        $this->currentRoute = $this->normalizeRoute($route);
        $this->currentMethod = 'POST';
        $this->registerRoute('POST', $route, $callback, $matchType);
        return $this;
    }

    public function put($route, $callback, $matchType = null)
    {
        $this->currentRoute = $this->normalizeRoute($route);
        $this->currentMethod = 'PUT';
        $this->registerRoute('PUT', $route, $callback, $matchType);
        return $this;
    }

    public function delete($route, $callback, $matchType = null)
    {
        $this->currentRoute = $this->normalizeRoute($route);
        $this->currentMethod = 'DELETE';
        $this->registerRoute('DELETE', $route, $callback, $matchType);
        return $this;
    }

    private function registerRoute($method, $route, $callback, $matchType)
    {
        $route = $this->normalizeRoute($route);

        // Détecter si la route contient des paramètres {param}
        if (preg_match('/\{(\w+)\}/', $route)) {
            $this->dynamicRoutes[$method][] = [
                'route' => $route,
                'callback' => $callback,
                'matchType' => 'parametrized'
            ];
        } elseif ($matchType === 'if-start' || $matchType === 'is-base') {
            $this->dynamicRoutes[$method][] = [
                'route' => $route,
                'callback' => $callback,
                'matchType' => $matchType
            ];
        } else {
            $this->routes[$method][$route] = $callback;
        }

        // Réinitialiser après enregistrement
        $this->currentRoute = '';
        $this->currentMethod = '';
    }

    private function normalizeRoute($route)
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

            $pattern = $this->buildPattern($route, $matchType);

            if (preg_match($pattern, $uri, $matches)) {
                $segments = $this->extractSegments($matches, $matchType);

                // Appliquer les middlewares spécifiques à la route
                if (isset($routeConfig['middlewares'])) {
                    $this->applyRouteMiddlewares($routeConfig['middlewares'], $segments);
                }

                $this->req->params($segments);

                $callback($this->req, $this->res);
                return true;
            }
        }
        return false;
    }

    /**
     * Applique les middlewares spécifiques à une route
     */
    private function applyRouteMiddlewares($middlewares, $segments)
    {
        foreach ($middlewares as $mwConfig) {
            $middlewareClass = $mwConfig['class'];
            $options = $mwConfig['options'];

            // Instancier et exécuter le middleware
            $middlewareInstance = new $middlewareClass(...$options);

            if (method_exists($middlewareInstance, 'process')) {
                $middlewareInstance->process($this->req, $this->res, function () use ($segments) {
                    // Fonction next() qui continue
                    return true;
                });
            }
        }
    }

    private function buildPattern($route, $matchType)
    {
        if ($matchType === 'parametrized') {
            $escapedRoute = preg_quote($route, '/');
            $pattern = preg_replace('/\\\\\{(\w+)\\\\\}/', '(?P<$1>[^\/]+)', $escapedRoute);
            return "/^" . $pattern . "$/";
        }

        $escapedRoute = preg_quote($route, '/');

        if ($matchType === 'if-start') {
            return "/^{$escapedRoute}\/([\w]+)(?:\/(.*))?$/";
        } elseif ($matchType === 'is-base') {
            return "/^{$escapedRoute}(?:\/(.*))?$/";
        }
        throw new \Exception("matchType doit être 'if-start', 'is-base' ou 'parametrized'");
    }

    private function extractSegments($matches, $matchType)
    {
        if ($matchType === 'parametrized') {
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }
            return $params;
        } elseif ($matchType === 'if-start') {
            $start = $matches[1] ?? '';
            $rest = isset($matches[2]) ? trim($matches[2], '/') : '';
            $segments = array_filter(explode('/', $rest));
            return array_merge([$start], $segments);
        } elseif ($matchType === 'is-base') {
            $rest = isset($matches[1]) ? trim($matches[1], '/') : '';
            return array_filter(explode('/', $rest));
        }
        return [];
    }

    /**
     * Exécute la chaîne de middlewares globaux
     */
    private function executeMiddlewares()
    {
        foreach ($this->globalMiddlewares as &$mwConfig) {
            $middlewareClass = $mwConfig['class'];
            $options = $mwConfig['options'];

            // Instancier le middleware (singleton)
            if ($mwConfig['instance'] === null) {
                $mwConfig['instance'] = new $middlewareClass(...$options);
            }

            $middlewareInstance = $mwConfig['instance'];

            // Différentes interfaces de middleware supportées
            if (method_exists($middlewareInstance, '__invoke')) {
                // Interface callable
                $next = function () {
                    return true;
                };
                $middlewareInstance($this->req, $next);
            } elseif (method_exists($middlewareInstance, 'handle')) {
                // Interface handle()
                $middlewareInstance->handle($this->req, $this->res);
            } elseif (method_exists($middlewareInstance, 'process')) {
                // Interface process(request, response, next)
                $middlewareInstance->process($this->req, $this->res, function () {
                    return true;
                });
            }
        }
    }

    public function run($http = "404", $function = null)
    {
        // 1. Exécuter les middlewares globaux
        $this->executeMiddlewares();

        // 2. Traiter la requête
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $this->normalizeRoute(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

        // 3. Vérifier les routes statiques avec middlewares
        if (isset($this->routes[$method][$uri])) {
            $routeConfig = $this->routes[$method][$uri];

            if (is_array($routeConfig) && isset($routeConfig['callback'])) {
                // Appliquer les middlewares de la route
                if (isset($routeConfig['middlewares'])) {
                    $this->applyRouteMiddlewares($routeConfig['middlewares'], []);
                }

                // Exécuter le callback
                $this->req->params([]);
                $routeConfig['callback']($this->req, $this->res);
            } else {
                // Route sans middleware
                $this->req->params([]);
                $routeConfig($this->req, $this->res);
            }
            return;
        }

        // 4. Vérifier les routes dynamiques
        if ($this->matchDynamicRoute($method, $uri)) {
            return;
        }

        // 5. Gérer les 404
        if ($http === '404') {
            http_response_code(404);
            if (is_callable($function)) {
                $function();
            }
            exit;
        } else {
            $segment = explode('/', $uri);
            if (is_callable($function)) {
                $this->req->params($segment);
                $function($this->req, $this->res);
            }
            return;
        }
    }
}