<?php

namespace Eorbahapi\Adapter\Server;

use Eorbahapi\Adapter\Http\RequestAdapter;
use Eorbahapi\Adapter\Http\ResponseAdapter;
use Eorbahapi\Adapter\WebSocket\SwooleWebSocket;
use Eorbahapi\Contracts\ESGIAppInterface;
use Eorbahapi\Contracts\ResponseInterface;
use Eorbahapi\Contracts\ServerAdapterInterface;
use Eorbahapi\EorbahAPI;
use Eorbahapi\Request;
use Eorbahapi\Response;
use Eorbahapi\Responses\FileResponse;
use Eorbahapi\Responses\StreamingResponse;
use Eorbahapi\Status;
use Eorbahapi\Websockets\WebSocketDisconnect;

use Swoole\HTTP\Server as SwooleServer;
use Swoole\WebSocket\Server as SwooleWebSocketServer;

/**
 * Adaptateur Swoole (serveur HTTP persistant et concurrent).
 *
 * L'extension `swoole` est optionnelle : l'adaptateur vérifie sa présence au
 * démarrage et échoue avec un message explicite sinon. Chaque requête Swoole
 * est convertie en `Eorbahapi\Request`, confiée à l'application, puis la réponse
 * est écrite avec l'API Swoole.
 *
 * Si l'application déclare des routes WebSocket (`$app->websocket(...)`), le
 * serveur est un `Swoole\WebSocket\Server` : le **même port** sert le HTTP
 * (`/health`, `/docs`…) et les connexions WebSocket (`ws://…/ws/echo`).
 *
 *     $app = new EorbahAPI();
 *     (new ESGIAppRunner())->run($app, '0.0.0.0', 8000, false, new SwooleServerAdapter(workers: 4));
 *
 * Swoole fournit `$request->server` avec des clés en minuscules
 * (`request_method`, `request_uri`…) : elles sont normalisées en majuscules pour
 * que `Eorbahapi\Request` (et donc le routeur) fonctionne comme sous PHP-FPM.
 */
final class SwooleServerAdapter implements ServerAdapterInterface
{
    /**
     * Seuil de journalisation (`debug`, `info`, `warning`, `error`, `none`).
     */
    private const LEVELS = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3, 'none' => 99];

    /** @var callable|null */
    private $logger;

    public function __construct(
        private readonly int $workers = 1,
        private readonly string $logLevel = 'info',
        private readonly bool $accessLog = true,
        ?callable $logger = null,
    ) {
        $this->logger = $logger;
    }

    public function run(ESGIAppInterface $app, string $host = '127.0.0.1', int $port = 8000, bool $reload = false): void
    {
        if (!extension_loaded('swoole')) {
            throw new \RuntimeException(
                "L'adaptateur Swoole nécessite l'extension « swoole » ; utilisez MinimalServerAdapter sinon."
            );
        }

        // Même port pour HTTP et WebSocket : `Swoole\WebSocket\Server` est un
        // serveur HTTP qui sait aussi gérer les connexions WebSocket.
        $withWebSockets = $app instanceof EorbahAPI && $app->websocket_routes()->count() > 0;

        $server = $withWebSockets
            ? new SwooleWebSocketServer($host, $port)
            : new SwooleServer($host, $port);

        $this->log('info', 'Started server process [' . getmypid() . ']');
        $this->log('info', 'Waiting for application startup.');
        $this->log('info', 'Application startup complete.');
        $this->log('info', 'EorbahAPI running on http://' . $host . ':' . $port . ' (Press CTRL+C to quit)');

        if ($withWebSockets) {
            $this->log('info', 'WebSocket support enabled on ws://' . $host . ':' . $port);
        }

        $server->on('shutdown', function (): void {
            $this->log('info', 'Shutting down');
        });

        $server->set([
            'worker_num' => $this->workers,
            // Les handlers WebSocket bloquants (`while ($ws->receive())`) ont
            // besoin des coroutines : `receive()` attend sur un Channel.
            'enable_coroutine' => $withWebSockets,
        ]);

        $server->on('request', function ($swooleRequest, $swooleResponse) use ($app): void {
            try {
                $this->handleRequest($swooleRequest, $swooleResponse, $app);
            } catch (\Throwable $error) {
                $this->log('error', 'Exception in HTTP handler: ' . $error->getMessage());
                $this->logAccess($swooleRequest, 500);
                $swooleResponse->status(500);
                $swooleResponse->header('Content-Type', 'application/json');
                $swooleResponse->end((string) json_encode([
                    'error' => true,
                    'status' => 500,
                    'message' => 'Internal Server Error',
                ]));
            }
        });

        if ($withWebSockets) {
            /** @var array<int, SwooleWebSocket> $connections */
            $connections = [];

            $server->on('open', function ($server, $request) use ($app, &$connections): void {
                $this->handleOpen($server, $request, $app, $connections);
            });

            $server->on('message', function ($server, $frame) use (&$connections): void {
                $this->handleMessage($frame, $connections);
            });

            $server->on('close', function ($server, $fd) use (&$connections): void {
                $this->handleClose((int) $fd, $connections);
            });
        }

        $server->start();

        $this->log('info', 'Finished server process [' . getmypid() . ']');
    }

    /**
     * Accepte une connexion WebSocket et lance le handler de route.
     *
     * @param array<int, SwooleWebSocket> $connections
     */
    public function handleOpen(object $server, object $request, ESGIAppInterface $app, array &$connections): void
    {
        if (!$app instanceof EorbahAPI) {
            $this->disconnect($server, (int) ($request->fd ?? 0), 1008, 'WebSocket non disponible');

            return;
        }

        $serverVars = [];
        foreach ((array) ($request->server ?? []) as $key => $value) {
            $serverVars[strtoupper((string) $key)] = $value;
        }

        $uri = (string) ($serverVars['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        $match = $app->websocket_routes()->match($path);
        $client = $this->clientAddress($request);

        if ($match === null) {
            if ($this->accessLog) {
                $this->log('warning', $client . ' - "WebSocket ' . $path . '" [rejected]');
            }

            $this->disconnect($server, (int) ($request->fd ?? 0), 1008, 'Route WebSocket introuvable');

            return;
        }

        if ($this->accessLog) {
            $this->log('info', $client . ' - "WebSocket ' . $path . '" [accepted]');
            $this->log('info', 'connection open');
        }

        $headers = [];
        foreach ((array) ($request->header ?? []) as $name => $value) {
            $headers[strtolower((string) $name)] = (string) $value;
        }

        $fd = (int) ($request->fd ?? 0);

        $websocket = new SwooleWebSocket(
            $server,
            $fd,
            $path,
            $headers,
            (array) ($request->get ?? []),
            $match['params'],
        );

        $connections[$fd] = $websocket;

        $handler = $match['handler'];

        $runner = function () use ($handler, $websocket): void {
            try {
                $handler($websocket);
            } catch (WebSocketDisconnect) {
                // Fermeture normale par le client.
            } catch (\Throwable $error) {
                $this->log('error', 'Exception in WebSocket handler: ' . $error->getMessage());
                $websocket->close(1011, 'Internal error');
            } finally {
                $websocket->close();
            }
        };

        if (
            function_exists('go')
            && class_exists(\Swoole\Coroutine::class)
            && \Swoole\Coroutine::getCid() >= 0
        ) {
            go($runner);

            return;
        }

        // Hors coroutine (tests, CLI) : exécution synchrone.
        $runner();
    }

    /**
     * Transmet une trame reçue au handler de la connexion.
     *
     * @param array<int, SwooleWebSocket> $connections
     */
    public function handleMessage(object $frame, array &$connections): void
    {
        $fd = (int) ($frame->fd ?? 0);

        if (!isset($connections[$fd])) {
            return;
        }

        $connections[$fd]->pushFragment(
            (string) ($frame->data ?? ''),
            (bool) ($frame->finish ?? true),
        );
    }

    /**
     * Signale la fermeture de la connexion au handler.
     *
     * @param array<int, SwooleWebSocket> $connections
     */
    public function handleClose(int $fd, array &$connections): void
    {
        if (!isset($connections[$fd])) {
            return;
        }

        $connections[$fd]->pushDisconnect(new WebSocketDisconnect(1000, 'Client fermé'));
        unset($connections[$fd]);

        if ($this->accessLog) {
            $this->log('info', 'connection closed');
        }
    }

    /**
     * Journalise une requête HTTP au format uvicorn.
     */
    private function logAccess(object $swooleRequest, int $status): void
    {
        if (!$this->accessLog) {
            return;
        }

        $server = [];
        foreach ((array) ($swooleRequest->server ?? []) as $key => $value) {
            $server[strtoupper((string) $key)] = $value;
        }

        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($server['REQUEST_URI'] ?? '/');
        $protocol = (string) ($server['SERVER_PROTOCOL'] ?? 'HTTP/1.1');

        $this->log(
            'info',
            $this->clientAddress($swooleRequest)
            . ' - "' . $method . ' ' . $uri . ' ' . $protocol . '" '
            . $status . ' ' . Status::text($status)
        );
    }

    /**
     * Adresse du client au format uvicorn : `('127.0.0.1', 53211)`.
     */
    private function clientAddress(object $request): string
    {
        $server = [];
        foreach ((array) ($request->server ?? []) as $key => $value) {
            $server[strtoupper((string) $key)] = $value;
        }

        $ip = $server['REMOTE_ADDR'] ?? null;
        $port = $server['REMOTE_PORT'] ?? null;

        if ($ip === null) {
            return 'unknown';
        }

        return $port !== null ? "('$ip', $port)" : "'$ip'";
    }

    /**
     * Écrit une ligne de journal au format uvicorn (`INFO:     message`).
     *
     * Le logger est injectable (tests, PSR-3) ; par défaut, écrit sur stderr.
     */
    private function log(string $level, string $message): void
    {
        $threshold = self::LEVELS[strtolower($this->logLevel)] ?? self::LEVELS['info'];
        $rank = self::LEVELS[strtolower($level)] ?? self::LEVELS['info'];

        if ($rank < $threshold) {
            return;
        }

        if ($this->logger !== null) {
            ($this->logger)($level, $message);

            return;
        }

        $line = str_pad(strtoupper($level) . ':', 9) . ' ' . $message;

        if (defined('STDERR')) {
            fwrite(STDERR, $line . PHP_EOL);

            return;
        }

        error_log($line);
    }

    private function disconnect(object $server, int $fd, int $code, string $reason): void
    {
        if (method_exists($server, 'disconnect')) {
            $server->disconnect($fd, $code, $reason);

            return;
        }

        if (method_exists($server, 'close')) {
            $server->close($fd);
        }
    }

    /**
     * Traite une requête Swoole et écrit la réponse.
     *
     * Extrait de `run()` pour rester testable sans extension : `$swooleRequest`
     * doit exposer les propriétés publiques `server`, `header`, `get`, `post`,
     * `cookie`, `files` et, si possible, `rawContent()`.
     */
    public function handleRequest(object $swooleRequest, object $swooleResponse, ESGIAppInterface $app): int
    {
        $concreteRequest = self::buildRequest($swooleRequest);

        // EorbahAPI expose `handle()` : on récupère la réponse concrète pour
        // supporter cookies et réponses en flux. Une application ESGI générique
        // passe par l'adaptateur.
        $response = $app instanceof EorbahAPI
            ? $app->handle($concreteRequest, new Response())
            : $app(new RequestAdapter($concreteRequest), ResponseAdapter::create());

        $this->writeResponse($response, $swooleResponse, $concreteRequest->method() === 'HEAD');
        $this->logAccess($swooleRequest, $response->status());

        return $response->status();
    }

    /**
     * Écrit statut, en-têtes, cookies et corps (ou flux) sur la réponse Swoole.
     */
    private function writeResponse(
        ResponseInterface|Response $response,
        object $swooleResponse,
        bool $headRequest,
    ): void {
        $swooleResponse->status($response->status());

        foreach ($response->headers() as $name => $value) {
            $swooleResponse->header((string) $name, (string) $value);
        }

        $concrete = $response instanceof ResponseAdapter ? $response->unwrap() : $response;

        if ($concrete instanceof Response) {
            foreach ($concrete->cookies as $cookie) {
                $options = $cookie['options'] + [
                    'expires' => 0,
                    'path' => '/',
                    'domain' => '',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Strict',
                ];

                $swooleResponse->cookie(
                    (string) $cookie['name'],
                    (string) $cookie['value'],
                    (int) $options['expires'],
                    (string) $options['path'],
                    (string) $options['domain'],
                    (bool) $options['secure'],
                    (bool) $options['httponly'],
                    (string) $options['samesite'],
                );
            }
        }

        if ($concrete instanceof FileResponse) {
            if ($headRequest || in_array($response->status(), [204, 304, 416], true)) {
                $swooleResponse->end();

                return;
            }

            if ($concrete->path() !== null && method_exists($swooleResponse, 'sendfile')) {
                $swooleResponse->sendfile(
                    (string) $concrete->path(),
                    $concrete->offset(),
                    $concrete->length() ?? 0,
                );

                return;
            }

            foreach ($concrete->streamChunks() as $chunk) {
                $swooleResponse->write($chunk);
            }

            $swooleResponse->end();

            return;
        }

        if ($concrete instanceof StreamingResponse) {
            foreach ($concrete->generator() as $chunk) {
                $swooleResponse->write((string) $chunk);
            }

            $swooleResponse->end();

            return;
        }

        if ($headRequest) {
            $swooleResponse->end();

            return;
        }

        $swooleResponse->end($response->text());
    }

    /**
     * Convertit une requête Swoole en `Eorbahapi\Request`.
     */
    public static function buildRequest(object $swooleRequest): Request
    {
        $server = self::normalizeServer((array) ($swooleRequest->server ?? []));

        $headers = [];
        foreach ((array) ($swooleRequest->header ?? []) as $name => $value) {
            $headers[(string) $name] = (string) $value;
        }

        $rawBody = method_exists($swooleRequest, 'rawContent')
            ? (string) $swooleRequest->rawContent()
            : '';

        $decodedBody = null;
        if ($rawBody !== '') {
            $decoded = json_decode($rawBody, true);
            $decodedBody = is_array($decoded) ? $decoded : null;
        }

        return new Request(
            body: $decodedBody,
            server: $server,
            query: (array) ($swooleRequest->get ?? []),
            postData: (array) ($swooleRequest->post ?? []),
            cookies: (array) ($swooleRequest->cookie ?? []),
            files: (array) ($swooleRequest->files ?? []),
            headers: $headers,
        );
    }

    /**
     * Swoole expose `$request->server` en minuscules ; `Eorbahapi\Request` lit
     * `$_SERVER`-like (`REQUEST_METHOD`, `REQUEST_URI`…).
     *
     * @param array<string, mixed> $server
     * @return array<string, mixed>
     */
    private static function normalizeServer(array $server): array
    {
        $normalized = [];

        foreach ($server as $key => $value) {
            $normalized[strtoupper((string) $key)] = $value;
        }

        $normalized['REQUEST_METHOD'] = strtoupper((string) ($normalized['REQUEST_METHOD'] ?? 'GET'));
        $normalized['REQUEST_URI'] = (string) ($normalized['REQUEST_URI'] ?? '/');

        if ($normalized['REQUEST_URI'] === '') {
            $normalized['REQUEST_URI'] = '/';
        }

        $path = parse_url($normalized['REQUEST_URI'], PHP_URL_PATH);
        $normalized['PATH_INFO'] ??= is_string($path) && $path !== '' ? $path : '/';

        return $normalized;
    }
}
