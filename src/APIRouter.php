<?php

namespace Eorbahapi;

class APIRouter {
    public array $routes = [];
    
    public function __construct(
        public readonly ?string $prefix = '', 
        public readonly array $tags = []
    ) {}

    public function get(string $route, callable $callback, array $middlewares = []) {
        $this->add_api_route($route, ['GET'], $callback, $middlewares);
    }
    public function post(string $route, callable $callback, array $middlewares = []) {
        $this->add_api_route($route, ['POST'], $callback, $middlewares);
    }
    public function put(string $route, callable $callback, array $middlewares = []) {
        $this->add_api_route($route, ['PUT'], $callback, $middlewares);
    }
    public function delete(string $route, callable $callback, array $middlewares = []) {
        $this->add_api_route($route, ['DELETE'], $callback, $middlewares);
    }

    public function add_api_route(string $route, $methods, callable $callback, array $middlewares = []): void {
        $methods = is_array($methods) ? $methods : explode('|', $methods);
        foreach ($methods as $method) {
            $this->routes[$method][$route] = [
                'callback' => $callback,
                'middlewares' => $middlewares
            ];
        }
    }

}