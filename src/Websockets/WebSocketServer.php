<?php

namespace Eorbahapi\Websockets;

use Eorbahapi\EorbahAPI;

/**
 * Serveur WebSocket autonome (RFC 6455), basé sur les streams PHP.
 *
 * Il accepte les connexions, lit la poignée de main HTTP, choisit la route
 * correspondante dans un `WebSocketRouter` et confie la connexion au handler.
 *
 *     $app->websocket('/ws/{room}', function (WebSocket $ws) {
 *         $ws->send('bienvenue dans ' . $ws->params()['room']);
 *     });
 *
 *     (new WebSocketServer('127.0.0.1', 8081))->run($app);
 *
 * Le serveur est volontairement synchrone et monotâche : un handler bloque la
 * boucle jusqu'à sa fin. Pour de la concurrence, branchez l'application sur
 * Swoole ou un autre serveur asynchrone.
 */
final class WebSocketServer
{
    public function __construct(
        private readonly string $host = '127.0.0.1',
        private readonly int $port = 8081,
    ) {
    }

    public function run(WebSocketRouter|EorbahAPI $routes): void
    {
        $router = $routes instanceof EorbahAPI ? $routes->websocket_routes() : $routes;

        $address = "tcp://{$this->host}:{$this->port}";
        $server = @stream_socket_server($address, $errno, $errstr);

        if ($server === false) {
            throw new \RuntimeException("Impossible d'écouter sur $address : $errstr ($errno).");
        }

        try {
            while (($client = @stream_socket_accept($server, -1)) !== false) {
                $this->handle($client, $router);
            }
        } finally {
            @fclose($server);
        }
    }

    /**
     * Traite une connexion brute (testable sans réseau avec
     * `stream_socket_pair()`).
     *
     * @param resource $client
     */
    public function handle($client, WebSocketRouter $router): void
    {
        stream_set_timeout($client, 5);

        $request = $this->readHandshake($client);

        if ($request === null) {
            @fclose($client);

            return;
        }

        $headers = $request['headers'];

        if (($headers['upgrade'] ?? '') !== 'websocket' || !isset($headers['sec-websocket-key'])) {
            $this->reject($client, 400, 'Bad Request');

            return;
        }

        $match = $router->match($request['path']);

        if ($match === null) {
            $this->reject($client, 404, 'Not Found');

            return;
        }

        $websocket = new WebSocket(
            $client,
            $request['path'],
            $headers,
            $request['query'],
            $match['params'],
            $request['client'] ?? null,
            $request['server'] ?? null,
        );

        try {
            $websocket->accept();
            ($match['handler'])($websocket);
        } catch (WebSocketDisconnect) {
            // Fermeture normale par le client : rien à signaler.
        } finally {
            if ($websocket->state() !== WebSocketState::CLOSED) {
                $websocket->close();
            }
        }
    }

    /**
     * @param resource $client
     * @return array{path: string, query: array<string, mixed>, headers: array<string, string>}|null
     */
    private function readHandshake($client): ?array
    {
        $buffer = '';

        while (!str_contains($buffer, "\r\n\r\n")) {
            $chunk = @fread($client, 2048);

            if ($chunk === false || $chunk === '') {
                return null;
            }

            $buffer .= $chunk;

            if (strlen($buffer) > 65536) {
                return null;
            }
        }

        [$head] = explode("\r\n\r\n", $buffer, 2);
        $lines = explode("\r\n", $head);
        $requestLine = array_shift($lines) ?? '';

        if (preg_match('#^[A-Z]+ (\S+) HTTP/1\.[01]$#', $requestLine, $matches) !== 1) {
            return null;
        }

        $uri = $matches[1];
        $path = parse_url($uri, PHP_URL_PATH);
        $queryString = parse_url($uri, PHP_URL_QUERY);

        $query = [];
        if (is_string($queryString) && $queryString !== '') {
            parse_str($queryString, $query);
        }

        $headers = [];
        foreach ($lines as $line) {
            $position = strpos($line, ':');

            if ($position === false) {
                continue;
            }

            $headers[strtolower(trim(substr($line, 0, $position)))] = trim(substr($line, $position + 1));
        }

        return [
            'path' => is_string($path) && $path !== '' ? $path : '/',
            'query' => $query,
            'headers' => $headers,
        ];
    }

    /**
     * @param resource $client
     */
    private function reject($client, int $status, string $reason): void
    {
        $body = $reason;

        $response = "HTTP/1.1 $status $reason\r\n"
            . "Connection: close\r\n"
            . "Content-Type: text/plain\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n"
            . "\r\n"
            . $body;

        @fwrite($client, $response);
        @fclose($client);
    }
}
