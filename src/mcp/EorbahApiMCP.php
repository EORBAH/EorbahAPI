<?php
namespace EorBah545\Eorbahapi\mcp;

use EorBah545\Eorbahapi\Request;
use EorBah545\Eorbahapi\Response;

class EorbahApiMCP
{
    private array $routes = [];
    private array $dynamicRoutes = [];
    private ?Request $request = null;
    private ?Response $response = null;
    private array $options;
    private MCPProtocolHandler $protocolHandler;
    private array $routeMiddlewares = [];

    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'name' => 'EorbahAPI MCP Server',
            'description' => 'MCP server exposing local routes as tools',
            'include_tags' => [],
            'exclude_tags' => [],
            'include_operations' => [],
            'exclude_operations' => [],
        ], $options);

        $this->protocolHandler = new MCPProtocolHandler($this, $this->options);
    }

    public function get(string $route, callable $callback): self
    {
        $this->registerRoute('GET', $route, $callback);
        return $this;
    }

    public function post(string $route, callable $callback): self
    {
        $this->registerRoute('POST', $route, $callback);
        return $this;
    }

    public function put(string $route, callable $callback): self
    {
        $this->registerRoute('PUT', $route, $callback);
        return $this;
    }

    public function delete(string $route, callable $callback): self
    {
        $this->registerRoute('DELETE', $route, $callback);
        return $this;
    }

    private function registerRoute(string $method, string $route, callable $callback): void
    {
        $route = $this->normalizeRoute($route);

        if (preg_match('/\{(\w+)(?::(\w+))?\}/', $route)) {
            $paramNames = [];
            preg_match_all('/\{(\w+)(?::\w+)?\}/', $route, $matches);
            $paramNames = $matches[1];
            $pattern = $this->buildPattern($route);

            $this->dynamicRoutes[$method][] = [
                'route' => $route,
                'callback' => $callback,
                'paramNames' => $paramNames,
                'pattern' => $pattern,
                'middlewares' => [],
            ];
        } else {
            $this->routes[$method][$route] = [
                'callback' => $callback,
                'middlewares' => [],
            ];
        }
    }

    /**
     * Construit une expression régulière pour une route contenant des paramètres.
     * Exemple : "/user/{id}" -> "/^\/user\/(?P<id>[^\/]+)$/"
     */
    private function buildPattern(string $route): string
    {
        // Découper la route en segments fixes et paramètres
        $parts = preg_split('/\{(\w+)(?::(\w+))?\}/', $route, -1, PREG_SPLIT_DELIM_CAPTURE);
        $pattern = '';
        $i = 0;
        $len = count($parts);
        while ($i < $len) {
            // Partie fixe
            $fixed = $parts[$i++];
            $pattern .= preg_quote($fixed, '/');
            if ($i < $len) {
                // Nom du paramètre
                $paramName = $parts[$i++];
                // Type optionnel (par défaut 'string')
                $paramType = ($i < $len && $parts[$i] !== '') ? $parts[$i++] : 'string';
                $regex = ($paramType === 'path') ? '.*' : '[^/]+';
                $pattern .= '(?P<' . $paramName . '>' . $regex . ')';
            }
        }
        return '/^' . $pattern . '$/';
    }

    public function handle(Request $request, Response $response): void
    {
        $this->request = $request;
        $this->response = $response;

        if ($request->method() === 'POST' && $this->isJsonRpcRequest()) {
            $this->protocolHandler->handle($request, $response);
            return;
        }

        $this->routeRequest();
    }

    private function routeRequest(): void
    {
        $method = $this->request->method();
        $uri = $this->request->path();

        if (isset($this->routes[$method][$uri])) {
            $route = $this->routes[$method][$uri];
            $this->executeCallback($route['callback'], $route['middlewares'] ?? []);
            return;
        }

        if ($this->matchDynamicRoute($method, $uri)) {
            return;
        }

        $this->response->status(404)->send('Not Found');
    }

    private function matchDynamicRoute(string $method, string $uri): bool
    {
        if (!isset($this->dynamicRoutes[$method])) {
            return false;
        }

        foreach ($this->dynamicRoutes[$method] as $routeConfig) {
            if (preg_match($routeConfig['pattern'], $uri, $matches)) {
                $params = [];
                foreach ($routeConfig['paramNames'] as $name) {
                    $params[$name] = $matches[$name] ?? null;
                }
                $this->request->params($params);
                $this->executeCallback($routeConfig['callback'], $routeConfig['middlewares'] ?? []);
                return true;
            }
        }
        return false;
    }

    private function executeCallback(callable $callback, array $middlewares = []): void
    {
        $ref = new \ReflectionFunction($callback);
        $args = [];
        foreach ($ref->getParameters() as $param) {
            $name = $param->getName();
            if ($name === 'request') {
                $args[] = $this->request;
            } elseif ($name === 'response') {
                $args[] = $this->response;
            } else {
                $args[] = $this->request->params($name) ?? null;
            }
        }
        $ref->invokeArgs($args);
    }

    private function normalizeRoute(string $route): string
    {
        return rtrim($route, '/');
    }

    private function isJsonRpcRequest(): bool
    {
        $contentType = $this->request->header('Content-Type') ?? '';
        if (strpos($contentType, 'application/json') === false) {
            return false;
        }
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) && isset($data['jsonrpc']) && $data['jsonrpc'] === '2.0';
    }

    public function setRequestResponse(Request $request, Response $response): void
    {
        $this->request = $request;
        $this->response = $response;
    }

    public function run($http = "404", $handler = null): void
    {
        if ($this->request && $this->response) {
            $this->handle($this->request, $this->response);
        } else {
            $this->handle(new Request(), new Response());
        }
    }

    public function getInternalRoutes(): array
    {
        $all = [];
        foreach ($this->routes as $method => $uris) {
            foreach ($uris as $uri => $config) {
                $all[] = [
                    'method' => $method,
                    'uri' => $uri,
                    'callback' => $config['callback'],
                    'isDynamic' => false,
                ];
            }
        }
        foreach ($this->dynamicRoutes as $method => $routes) {
            foreach ($routes as $routeConfig) {
                $all[] = [
                    'method' => $method,
                    'uri' => $routeConfig['route'],
                    'callback' => $routeConfig['callback'],
                    'isDynamic' => true,
                    'paramNames' => $routeConfig['paramNames'],
                    'pattern' => $routeConfig['pattern'],
                ];
            }
        }
        return $all;
    }

    public function executeCallbackWithArgs(callable $callback, array $arguments)
    {
        ob_start();
        $ref = new \ReflectionFunction($callback);
        $args = [];
        foreach ($ref->getParameters() as $param) {
            $name = $param->getName();
            if ($name === 'request') {
                $args[] = $this->request;
            } elseif ($name === 'response') {
                $args[] = $this->response;
            } elseif (array_key_exists($name, $arguments)) {
                $args[] = $arguments[$name];
            } else {
                $args[] = null;
            }
        }
        $result = $ref->invokeArgs($args);
        $output = ob_get_clean();
        if ($output) {
            return $output;
        }
        return $result;
    }
}