<?php

namespace Eorbahapi\Routing;

use Eorbahapi\Requests\Request;
use Eorbahapi\Responses\Response; 
use Eorbahapi\Responses\JSONResponse;

class APIRouter
{
    /**
     * Structure: ['GET' => ['/users' => $callback, ...], ...]
     * @var array
     */
    private array $routes = [];

    public function __construct(
        public $response_class = JSONResponse::class,
        public ?string $prefix = "",
        public ?array $tags = null,
        public ?string $description = null
    ) {}

    public function add_route(string $method, string $path, callable $handler): void
    {
        $this->routes[strtoupper($method)][$this->prefix . $path] = $handler;
    }

    public function get(string $path, callable $handler): void
    {
        $this->add_route('GET', $path, $handler);
    }
    public function post(string $path, callable $handler): void
    {
        $this->add_route('POST', $path, $handler);
    }
    public function put(string $path, callable $handler): void
    {
        $this->add_route('PUT', $path, $handler);
    }
    public function delete(string $path, callable $handler): void
    {
        $this->add_route('DELETE', $path, $handler);
    }
    public function options(string $path, callable $handler): void
    {
        $this->add_route('OPTIONS', $path, $handler);
    }
    public function head(string $path, callable $handler): void
    {
        $this->add_route('HEAD', $path, $handler);
    }
    public function patch(string $path, callable $handler): void
    {
        $this->add_route('PATCH', $path, $handler);
    }

    public function dispatch(Request $request): ?Response
    {
        $method = $request->getMethod();
        $path = parse_url($request->getUri(), PHP_URL_PATH);

        if (!isset($this->routes[$method][$path])) {
            return null; // Route non trouvée
        }

        $handler = $this->routes[$method][$path];
        // Injection de dependances
        return $this->callHandler($handler, $request);
    }

    private function callHandler(callable $handler, Request $request): Response
    {
        /**
         * Version simple : on appelle le handler en lui passant la Request
         * @var mixed
         */
        $result = $handler($request);

        if ($result instanceof Response) {
            return $result;
        }

        /**
         * Si le handler retourne autre chose (array, string), on construit une Response
         */
        if (is_array($result)) {
            return new $this->response_class(data: $result);
        }
        return new Response((string) $result);
    }
}