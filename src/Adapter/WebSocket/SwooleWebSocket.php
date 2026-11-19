<?php

namespace Eorbahapi\Adapter\WebSocket;

use Eorbahapi\Contracts\WebSocket\WebSocketInterface;
use Eorbahapi\Websockets\WebSocketDisconnect;
use Eorbahapi\Websockets\WebSocketState;

/**
 * Connexion WebSocket Swoole, côté serveur.
 *
 * Swoole est événementiel : `on('message')` pousse les trames dans une file, et
 * le handler de route les consomme avec `receive()`. Avec les coroutines
 * activées, `receive()` attend sur un `Channel` ; sans coroutine (tests), la
 * file est consommée sans blocage.
 *
 * Le même port peut servir HTTP et WebSocket : `SwooleServerAdapter` choisit un
 * `Swoole\WebSocket\Server` lorsque l'application déclare des routes WS.
 */
final class SwooleWebSocket implements WebSocketInterface
{
    private WebSocketState $state = WebSocketState::OPEN;

    /** @var object|null Canal Swoole (`Swoole\Coroutine\Channel`) si disponible. */
    private ?object $channel = null;

    /** @var list<mixed> File de secours (tests sans extension). */
    private array $queue = [];

    /** Tampon de fragmentation. */
    private string $fragment = '';

    /** Fermeture reçue mais pas encore lue par le handler. */
    private ?WebSocketDisconnect $pendingDisconnect = null;

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $query
     * @param array<string, string> $params
     */
    public function __construct(
        private readonly object $server,
        private readonly int $fd,
        private readonly string $path,
        private readonly array $headers = [],
        private readonly array $query = [],
        private readonly array $params = [],
    ) {
    }

    public function type(): string
    {
        return 'websocket';
    }

    public function state(): WebSocketState
    {
        return $this->state;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function query(): array
    {
        return $this->query;
    }

    public function params(): array
    {
        return $this->params;
    }

    public function client(): ?string
    {
        return null;
    }

    public function server(): ?string
    {
        return null;
    }

    public function send(string $message): void
    {
        $this->pushToClient($message, 0x1);
    }

    public function send_json(mixed $data): void
    {
        $this->send((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function send_bytes(string $data): void
    {
        $this->pushToClient($data, 0x2);
    }

    public function receive(): ?string
    {
        if ($this->pendingDisconnect !== null) {
            $disconnect = $this->pendingDisconnect;
            $this->pendingDisconnect = null;
            $this->state = WebSocketState::CLOSED;

            throw $disconnect;
        }

        if ($this->state === WebSocketState::CLOSED) {
            return null;
        }

        $item = $this->popMessage();

        if ($item instanceof WebSocketDisconnect) {
            $this->state = WebSocketState::CLOSED;

            throw $item;
        }

        if ($item === null || $item === false) {
            $this->state = WebSocketState::CLOSED;

            return null;
        }

        return (string) $item;
    }

    public function receive_json(): ?array
    {
        $message = $this->receive();

        if ($message === null || $message === '') {
            return null;
        }

        $decoded = json_decode($message, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function receive_bytes(): ?string
    {
        return $this->receive();
    }

    public function close(int $code = 1000, string $reason = ''): void
    {
        if ($this->state === WebSocketState::CLOSED) {
            return;
        }

        $this->state = WebSocketState::CLOSED;

        if (method_exists($this->server, 'disconnect')) {
            $this->server->disconnect($this->fd, $code, $reason);

            return;
        }

        if (method_exists($this->server, 'close')) {
            $this->server->close($this->fd);
        }
    }

    /**
     * Pousse une trame reçue vers le handler (appelé par `on('message')`).
     */
    public function pushFragment(string $data, bool $finish = true): void
    {
        $this->fragment .= $data;

        if (!$finish) {
            return;
        }

        $message = $this->fragment;
        $this->fragment = '';

        $this->pushMessage($message);
    }

    /**
     * Signale la fermeture de la connexion au handler.
     */
    public function pushDisconnect(WebSocketDisconnect $disconnect): void
    {
        $this->pendingDisconnect = $disconnect;
        $this->state = WebSocketState::CLOSED;

        if ($this->channel !== null) {
            // Ne bloque pas le worker si la file est pleine : le handler lira
            // `pendingDisconnect` à son prochain appel.
            if (!$this->channel->push($disconnect, 0.1)) {
                $this->channel->close();
            }

            return;
        }

        $this->queue[] = $disconnect;
    }

    /**
     * Alimente la file lue par `receive()`.
     */
    public function pushMessage(mixed $message): void
    {
        if ($this->channel === null && $this->inCoroutine() && class_exists(\Swoole\Coroutine\Channel::class)) {
            $this->channel = new \Swoole\Coroutine\Channel(1);
        }

        if ($this->channel !== null) {
            $this->channel->push($message);

            return;
        }

        $this->queue[] = $message;
    }

    private function popMessage(): mixed
    {
        if ($this->queue !== []) {
            return array_shift($this->queue);
        }

        if ($this->channel === null && $this->inCoroutine() && class_exists(\Swoole\Coroutine\Channel::class)) {
            $this->channel = new \Swoole\Coroutine\Channel(1);
        }

        if ($this->channel !== null) {
            return $this->channel->pop();
        }

        return null;
    }

    private function inCoroutine(): bool
    {
        return class_exists(\Swoole\Coroutine::class) && \Swoole\Coroutine::getCid() >= 0;
    }

    private function pushToClient(string $data, int $opcode): void
    {
        if ($this->state === WebSocketState::CLOSED) {
            throw new \RuntimeException('La connexion WebSocket est fermée.');
        }

        if (method_exists($this->server, 'isEstablished') && !$this->server->isEstablished($this->fd)) {
            $this->state = WebSocketState::CLOSED;

            throw new \RuntimeException('La connexion WebSocket n\'est plus établie.');
        }

        $this->server->push($this->fd, $data, $opcode);
    }
}
