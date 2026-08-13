<?php

namespace EorBah545\Eorbahapi;

class EorbahAPI {
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

    public function __construct(string $title = "EorbahAPI application") {
        $this->title = $title;
        $this->request = new Request();
        $this->response = new Response();
    }

    public function get($route, $callback): static {
        $this->currentRoute = $this->normalizeRoute($route);
        $this->currentMethod = 'GET';
        $this->registerRoute('GET', $route, $callback);
        return $this;
    }

    private function registerRoute($method, $route, $callback): void {
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
     * Normalise une route (supprime le slash final)
     */
    private function normalizeRoute($route): string {
        return rtrim($route, '/');
    }

    public function run($http_code = "404", $handler = null): void {
        try {
            $uri = $this->request->path();
            $method = $this->request->method();

            if(isset($this->routes[$method][$uri])) {
                $routeConfig = $this->routes[$method][$uri];
                $callback = $routeConfig;

                if (is_array($routeConfig) && isset($routeConfig['callback'])) {
                    $callback = $routeConfig['callback'];
                }

                $result = call_user_func_array($callback, [$this->response]);
                $headers = $this->response->headers;
                
                foreach ($headers as $headerName => $headerValue ) {
                    $this->response->setHeader($headerName, $headerValue);
                }
                 
                if (is_array($result) || is_object($result)) {
                    $this->response->json($result);
                } else {
                    echo $result;
                }
                
            }
        } catch (\Throwable $e) {
            echo json_encode([
                "error" => $e
            ]);
        }
    }
}