<?php

namespace Eorbahapi\Tests;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Websockets\WebSocket;
use Eorbahapi\Websockets\WebSocketDisconnect;
use Eorbahapi\Websockets\WebSocketRouter;
use Eorbahapi\Websockets\WebSocketServer;
use Eorbahapi\Websockets\WebSocketState;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WebSocketTest extends TestCase
{
    /**
     * @return array{0: resource, 1: resource}
     */
    private function pair(): array
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $this->assertIsArray($pair);

        return $pair;
    }

    private function maskedFrame(int $opcode, string $payload, bool $fin = true, string $mask = 'abcd'): string
    {
        $length = strlen($payload);
        $frame = chr(($fin ? 0x80 : 0x00) | $opcode);

        if ($length < 126) {
            $frame .= chr(0x80 | $length);
        } elseif ($length <= 0xFFFF) {
            $frame .= chr(0x80 | 126) . pack('n', $length);
        } else {
            $frame .= chr(0x80 | 127) . pack('N', 0) . pack('N', $length);
        }

        $frame .= $mask;

        for ($i = 0; $i < $length; $i++) {
            $frame .= chr(ord($payload[$i]) ^ ord($mask[$i % 4]));
        }

        return $frame;
    }

    /**
     * @param resource $stream
     * @return array{0: int, 1: string}|null
     */
    private function readFrame($stream): ?array
    {
        $header = $this->readBytes($stream, 2);

        if ($header === null) {
            return null;
        }

        $opcode = ord($header[0]) & 0x0F;
        $length = ord($header[1]) & 0x7F;

        if ($length === 126) {
            $extended = $this->readBytes($stream, 2);
            $length = $extended === null ? 0 : (int) unpack('n', $extended)[1];
        } elseif ($length === 127) {
            $extended = $this->readBytes($stream, 8);
            $parts = $extended === null ? [1 => 0, 2 => 0] : unpack('N2', $extended);
            $length = ((int) $parts[1] << 32) | (int) $parts[2];
        }

        $payload = $this->readBytes($stream, $length);

        return $payload === null ? null : [$opcode, $payload];
    }

    /**
     * @param resource $stream
     */
    private function readBytes($stream, int $length): ?string
    {
        $buffer = '';

        while (strlen($buffer) < $length) {
            $chunk = fread($stream, $length - strlen($buffer));

            if ($chunk === false || $chunk === '') {
                return null;
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }

    private function handshakeRequest(string $path, bool $websocket = true): string
    {
        $headers = [
            'Host: localhost',
            'Sec-WebSocket-Version: 13',
            'Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==',
        ];

        if ($websocket) {
            $headers[] = 'Upgrade: websocket';
            $headers[] = 'Connection: Upgrade';
        }

        return "GET $path HTTP/1.1\r\n" . implode("\r\n", $headers) . "\r\n\r\n";
    }

    #[Test]
    public function the_accept_key_matches_the_rfc_vector(): void
    {
        $this->assertSame(
            's3pPLMBiTxaQ9kYGzzhZRbK+xOo=',
            WebSocket::computeAccept('dGhlIHNhbXBsZSBub25jZQ==')
        );
    }

    #[Test]
    public function the_router_matches_paths_and_extracts_parameters(): void
    {
        $router = new WebSocketRouter();
        $router->add('/ws/rooms/{room}', static fn () => null);

        $match = $router->match('/ws/rooms/salon1');

        $this->assertNotNull($match);
        $this->assertSame('salon1', $match['params']['room']);
        $this->assertNull($router->match('/inconnu'));
        $this->assertTrue($router->has('/ws/rooms/a'));
        $this->assertSame(1, $router->count());
    }

    #[Test]
    public function eorbahapi_registers_websocket_routes(): void
    {
        $app = new EorbahAPI('WebSocket');
        $app->websocket('/ws/{room}', static fn () => null);

        $this->assertSame(1, $app->websocket_routes()->count());
        $this->assertSame('salon', $app->websocket_routes()->match('/ws/salon')['params']['room']);
    }

    #[Test]
    public function the_handshake_switches_protocols(): void
    {
        [$server, $client] = $this->pair();
        $websocket = new WebSocket($server, '/ws', [
            'sec-websocket-key' => 'dGhlIHNhbXBsZSBub25jZQ==',
        ]);

        $this->assertSame(WebSocketState::CONNECTING, $websocket->state());

        $websocket->accept();

        $response = $this->readBytes($client, 129);

        $this->assertStringContainsString('101 Switching Protocols', (string) $response);
        $this->assertStringContainsString('Sec-WebSocket-Accept: s3pPLMBiTxaQ9kYGzzhZRbK+xOo=', (string) $response);
        $this->assertSame(WebSocketState::OPEN, $websocket->state());
    }

    #[Test]
    public function text_frames_are_received_and_sent(): void
    {
        [$server, $client] = $this->pair();
        $websocket = $this->accepted($server, $client);

        fwrite($client, $this->maskedFrame(0x1, 'bonjour'));

        $this->assertSame('bonjour', $websocket->receive());

        $websocket->send('salut');

        [$opcode, $payload] = $this->readFrame($client);

        $this->assertSame(0x1, $opcode);
        $this->assertSame('salut', $payload);
    }

    #[Test]
    public function binary_and_json_messages_are_supported(): void
    {
        [$server, $client] = $this->pair();
        $websocket = $this->accepted($server, $client);

        fwrite($client, $this->maskedFrame(0x2, "\x00\x01\x02"));

        $this->assertSame("\x00\x01\x02", $websocket->receive_bytes());

        $websocket->send_json(['ok' => true]);

        [$opcode, $payload] = $this->readFrame($client);

        $this->assertSame(0x1, $opcode);
        $this->assertSame(['ok' => true], json_decode((string) $payload, true));

        fwrite($client, $this->maskedFrame(0x1, '{"nom":"Oumar"}'));

        $this->assertSame(['nom' => 'Oumar'], $websocket->receive_json());
    }

    #[Test]
    public function fragmented_messages_are_reassembled(): void
    {
        [$server, $client] = $this->pair();
        $websocket = $this->accepted($server, $client);

        fwrite(
            $client,
            $this->maskedFrame(0x1, 'bon', false)
            . $this->maskedFrame(0x0, 'jour', true)
        );

        $this->assertSame('bonjour', $websocket->receive());
    }

    #[Test]
    public function ping_frames_are_answered_with_pong(): void
    {
        [$server, $client] = $this->pair();
        $websocket = $this->accepted($server, $client);

        fwrite($client, $this->maskedFrame(0x9, 'p') . $this->maskedFrame(0x1, 'ok'));

        $this->assertSame('ok', $websocket->receive());

        [$opcode, $payload] = $this->readFrame($client);

        $this->assertSame(0xA, $opcode);
        $this->assertSame('p', $payload);
    }

    #[Test]
    public function a_close_frame_raises_websocket_disconnect(): void
    {
        [$server, $client] = $this->pair();
        $websocket = $this->accepted($server, $client);

        fwrite($client, $this->maskedFrame(0x8, pack('n', 1001)));

        try {
            $websocket->receive();
            $this->fail('WebSocketDisconnect attendu.');
        } catch (WebSocketDisconnect $disconnect) {
            $this->assertSame(1001, $disconnect->closeCode);
        }

        [$opcode] = $this->readFrame($client);

        $this->assertSame(0x8, $opcode);
        $this->assertSame(WebSocketState::CLOSED, $websocket->state());
    }

    #[Test]
    public function end_of_stream_returns_null(): void
    {
        [$server, $client] = $this->pair();
        $websocket = $this->accepted($server, $client);

        fclose($client);

        $this->assertNull($websocket->receive());
        $this->assertSame(WebSocketState::CLOSED, $websocket->state());
    }

    #[Test]
    public function the_server_handshakes_and_dispatches_to_the_route(): void
    {
        $router = new WebSocketRouter();
        $router->add('/ws/{room}', static function (WebSocket $websocket): void {
            $websocket->send('salon ' . $websocket->params()['room']);
        });

        [$serverEnd, $clientEnd] = $this->pair();
        fwrite($clientEnd, $this->handshakeRequest('/ws/salon1'));

        (new WebSocketServer())->handle($serverEnd, $router);

        $response = (string) stream_get_contents($clientEnd);

        $this->assertStringContainsString('101 Switching Protocols', $response);
        $this->assertStringContainsString('salon salon1', $response);
    }

    #[Test]
    public function the_server_rejects_unknown_routes(): void
    {
        [$serverEnd, $clientEnd] = $this->pair();
        fwrite($clientEnd, $this->handshakeRequest('/inconnu'));

        (new WebSocketServer())->handle($serverEnd, new WebSocketRouter());

        $response = (string) stream_get_contents($clientEnd);

        $this->assertStringContainsString('404', $response);
    }

    #[Test]
    public function the_server_rejects_plain_http_requests(): void
    {
        [$serverEnd, $clientEnd] = $this->pair();
        fwrite($clientEnd, $this->handshakeRequest('/ws', websocket: false));

        $router = new WebSocketRouter();
        $router->add('/ws', static fn () => null);

        (new WebSocketServer())->handle($serverEnd, $router);

        $response = (string) stream_get_contents($clientEnd);

        $this->assertStringContainsString('400', $response);
    }

    private function accepted($server, $client): WebSocket
    {
        $websocket = new WebSocket($server, '/ws', [
            'sec-websocket-key' => 'dGhlIHNhbXBsZSBub25jZQ==',
        ]);

        $websocket->accept();

        // Consomme la réponse de poignée de main.
        $this->readBytes($client, 129);

        return $websocket;
    }
}
