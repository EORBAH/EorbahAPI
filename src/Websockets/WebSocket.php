<?php

namespace Eorbahapi\Websockets;

use Eorbahapi\Contracts\WebSocket\WebSocketInterface;

/**
 * Connexion WebSocket (RFC 6455) : poignée de main, trames, envoi et réception.
 *
 * Un `WebSocket` enveloppe un flux TCP déjà accepté. Le serveur construit
 * l'objet avec les en-têtes de la poignée de main, appelle `accept()` puis le
 * handler ; le handler peut ensuite `send()`, `receive()` et `close()`.
 *
 *     $ws->accept();
 *     $ws->send('bonjour');
 *     $message = $ws->receive();
 *     $ws->close();
 */
final class WebSocket implements WebSocketInterface
{
    /** GUID du protocole, utilisé pour calculer `Sec-WebSocket-Accept`. */
    public const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    private const OP_CONTINUATION = 0x0;
    private const OP_TEXT = 0x1;
    private const OP_BINARY = 0x2;
    private const OP_CLOSE = 0x8;
    private const OP_PING = 0x9;
    private const OP_PONG = 0xA;

    private WebSocketState $state = WebSocketState::CONNECTING;

    /** Dernier type de trame de données reçu (texte ou binaire). */
    private int $lastOpcode = self::OP_TEXT;

    /**
     * @param resource             $stream  Flux TCP accepté.
     * @param array<string,string> $headers En-têtes de la poignée de main (clés en minuscules).
     * @param array<string,mixed>  $query   Paramètres de la query.
     * @param array<string,string> $params  Paramètres extraits du chemin.
     */
    public function __construct(
        private $stream,
        private readonly string $path = '/',
        private readonly array $headers = [],
        private readonly array $query = [],
        private readonly array $params = [],
        private readonly ?string $client = null,
        private readonly ?string $server = null,
    ) {
    }

    /**
     * Calcule la valeur de `Sec-WebSocket-Accept` (RFC 6455 § 4.2.2).
     */
    public static function computeAccept(string $key): string
    {
        return base64_encode(sha1(trim($key) . self::GUID, true));
    }

    public function state(): WebSocketState
    {
        return $this->state;
    }

    public function type(): string
    {
        return 'websocket';
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
        return $this->client;
    }

    public function server(): ?string
    {
        return $this->server;
    }

    /**
     * Répond à la poignée de main HTTP par `101 Switching Protocols`.
     */
    public function accept(): void
    {
        if ($this->state !== WebSocketState::CONNECTING) {
            throw new \RuntimeException('La poignée de main a déjà été traitée.');
        }

        $key = $this->headers['sec-websocket-key'] ?? null;

        if (!is_string($key) || $key === '') {
            throw new \RuntimeException("En-tête « Sec-WebSocket-Key » manquant.");
        }

        $response = "HTTP/1.1 101 Switching Protocols\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . 'Sec-WebSocket-Accept: ' . self::computeAccept($key) . "\r\n"
            . "\r\n";

        $this->write($response);
        $this->state = WebSocketState::OPEN;
    }

    /**
     * Envoie un message texte.
     */
    public function send(string $message): void
    {
        $this->assertOpen();
        $this->sendFrame(self::OP_TEXT, $message);
    }

    /**
     * Envoie une valeur en JSON (sous forme de texte).
     */
    public function send_json(mixed $data): void
    {
        $this->send((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * Envoie un message binaire.
     */
    public function send_bytes(string $data): void
    {
        $this->assertOpen();
        $this->sendFrame(self::OP_BINARY, $data);
    }

    /**
     * Reçoit le prochain message (texte ou binaire).
     *
     * @throws WebSocketDisconnect quand le pair ferme la connexion.
     */
    public function receive(): ?string
    {
        $payload = '';
        $opcode = self::OP_TEXT;

        while (true) {
            $frame = $this->readFrame();

            if ($frame === null) {
                $this->markClosed();

                return null;
            }

            [$fin, $frameOpcode, $data] = $frame;

            if ($frameOpcode === self::OP_CLOSE) {
                $code = 1000;

                if (strlen($data) >= 2) {
                    $code = (int) unpack('n', substr($data, 0, 2))[1];
                }

                $reason = strlen($data) > 2 ? substr($data, 2) : '';

                if ($this->state === WebSocketState::OPEN) {
                    $this->sendFrame(self::OP_CLOSE, pack('n', $code));
                }

                $this->markClosed();

                throw new WebSocketDisconnect($code, $reason);
            }

            if ($frameOpcode === self::OP_PING) {
                $this->sendFrame(self::OP_PONG, $data);
                continue;
            }

            if ($frameOpcode === self::OP_PONG) {
                continue;
            }

            if ($frameOpcode !== self::OP_CONTINUATION) {
                $opcode = $frameOpcode;
            }

            $payload .= $data;
            $this->lastOpcode = $opcode;

            if ($fin) {
                return $payload;
            }
        }
    }

    /**
     * Reçoit le prochain message JSON.
     *
     * @return array<string, mixed>|null
     */
    public function receive_json(): ?array
    {
        $message = $this->receive();

        if ($message === null || $message === '') {
            return null;
        }

        $decoded = json_decode($message, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Reçoit le prochain message binaire (ou texte) tel quel.
     */
    public function receive_bytes(): ?string
    {
        return $this->receive();
    }

    /**
     * Ferme la connexion proprement (trame `close` puis fermeture du flux).
     */
    public function close(int $code = 1000, string $reason = ''): void
    {
        if ($this->state === WebSocketState::CLOSED) {
            return;
        }

        if ($this->state === WebSocketState::OPEN) {
            $this->sendFrame(self::OP_CLOSE, pack('n', $code) . $reason);
        }

        $this->markClosed();
    }

    private function assertOpen(): void
    {
        if ($this->state !== WebSocketState::OPEN) {
            throw new \RuntimeException(
                'La connexion WebSocket n\'est pas ouverte (état : ' . $this->state->value . ').'
            );
        }
    }

    private function markClosed(): void
    {
        if ($this->state !== WebSocketState::CLOSED) {
            $this->state = WebSocketState::CLOSED;

            if (is_resource($this->stream)) {
                @fclose($this->stream);
            }
        }
    }

    /**
     * Encode et écrit une trame serveur (non masquée).
     */
    private function sendFrame(int $opcode, string $payload): void
    {
        $length = strlen($payload);
        $header = chr(0x80 | $opcode);

        if ($length < 126) {
            $header .= chr($length);
        } elseif ($length <= 0xFFFF) {
            $header .= chr(126) . pack('n', $length);
        } else {
            $high = intdiv($length, 0x100000000);
            $low = $length % 0x100000000;
            $header .= chr(127) . pack('N', $high) . pack('N', $low);
        }

        $this->write($header . $payload);
    }

    /**
     * Lit une trame complète.
     *
     * @return array{0: bool, 1: int, 2: string}|null
     */
    private function readFrame(): ?array
    {
        $first = $this->readBytes(2);

        if ($first === null) {
            return null;
        }

        $firstByte = ord($first[0]);
        $secondByte = ord($first[1]);

        $fin = ($firstByte & 0x80) !== 0;
        $opcode = $firstByte & 0x0F;
        $masked = ($secondByte & 0x80) !== 0;
        $length = $secondByte & 0x7F;

        if ($length === 126) {
            $extended = $this->readBytes(2);

            if ($extended === null) {
                return null;
            }

            $length = (int) unpack('n', $extended)[1];
        } elseif ($length === 127) {
            $extended = $this->readBytes(8);

            if ($extended === null) {
                return null;
            }

            $parts = unpack('N2', $extended);
            $length = ((int) $parts[1] << 32) | (int) $parts[2];
        }

        $mask = null;

        if ($masked) {
            $mask = $this->readBytes(4);

            if ($mask === null) {
                return null;
            }
        }

        $payload = $length > 0 ? $this->readBytes($length) : '';

        if ($payload === null) {
            return null;
        }

        if ($mask !== null) {
            $unmasked = '';

            for ($i = 0; $i < $length; $i++) {
                $unmasked .= chr(ord($payload[$i]) ^ ord($mask[$i % 4]));
            }

            $payload = $unmasked;
        }

        return [$fin, $opcode, $payload];
    }

    private function readBytes(int $length): ?string
    {
        $buffer = '';

        while (strlen($buffer) < $length) {
            $chunk = @fread($this->stream, $length - strlen($buffer));

            if ($chunk === false || $chunk === '') {
                return null;
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }

    private function write(string $data): void
    {
        $written = 0;
        $length = strlen($data);

        while ($written < $length) {
            $bytes = @fwrite($this->stream, substr($data, $written));

            if ($bytes === false || $bytes === 0) {
                throw new \RuntimeException('Écriture impossible sur la connexion WebSocket.');
            }

            $written += $bytes;
        }
    }
}
