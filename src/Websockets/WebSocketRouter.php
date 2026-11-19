<?php

namespace Eorbahapi\Websockets;

/**
 * Routes WebSocket, enregistrées séparément des routes HTTP.
 *
 *     $app->websocket('/ws/rooms/{room}', function (WebSocket $ws) {
 *         foreach (['a', 'b'] as $message) {
 *             $ws->send($message);
 *         }
 *     });
 *
 *     (new WebSocketServer())->run($app->websocket_routes());
 */
final class WebSocketRouter
{
    /** @var list<array{route: string, pattern: string, handler: callable}> */
    private array $routes = [];

    public function add(string $route, callable $handler): self
    {
        $route = $this->normalize($route);
        $this->routes[] = [
            'route' => $route,
            'pattern' => $this->buildPattern($route),
            'handler' => $handler,
        ];

        return $this;
    }

    /**
     * @return array{handler: callable, params: array<string, string>}|null
     */
    public function match(string $path): ?array
    {
        $path = $this->normalize($path);

        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $path, $matches) !== 1) {
                continue;
            }

            $params = [];

            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            return ['handler' => $route['handler'], 'params' => $params];
        }

        return null;
    }

    public function has(string $path): bool
    {
        return $this->match($path) !== null;
    }

    /** @return list<array{route: string, pattern: string, handler: callable}> */
    public function routes(): array
    {
        return $this->routes;
    }

    public function count(): int
    {
        return count($this->routes);
    }

    public function clear(): void
    {
        $this->routes = [];
    }

    private function normalize(string $path): string
    {
        $path = '/' . ltrim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function buildPattern(string $route): string
    {
        $escaped = preg_quote($route, '/');

        $pattern = preg_replace_callback(
            '/\\\\\{(\w+)(?:\\\\:(\w+))?\\\\\}/',
            static function (array $matches): string {
                $capture = ($matches[2] ?? null) === 'path' ? '.*' : '[^\\/]+';

                return '(?P<' . $matches[1] . '>' . $capture . ')';
            },
            $escaped
        );

        return '/^' . $pattern . '$/';
    }
}
