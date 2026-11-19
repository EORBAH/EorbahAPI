<?php

namespace Eorbahapi\Tests;

use Eorbahapi\Adapter\Server\SwooleServerAdapter;
use Eorbahapi\Adapter\WebSocket\SwooleWebSocket;
use Eorbahapi\Contracts\WebSocket\WebSocketInterface;
use Eorbahapi\EorbahAPI;
use Eorbahapi\Websockets\WebSocketDisconnect;
use Eorbahapi\Websockets\WebSocketState;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Serveur Swoole factice : enregistre les push et les déconnexions.
 */
final class FakeSwooleWsServer
{
    /** @var list<array{fd: int, data: string, opcode: int}> */
    public array $pushed = [];

    /** @var list<array{fd: int, code: int, reason: string}> */
    public array $disconnected = [];

    public function push(int $fd, string $data, int $opcode = 0x1): bool
    {
        $this->pushed[] = ['fd' => $fd, 'data' => $data, 'opcode' => $opcode];

        return true;
    }

    public function isEstablished(int $fd): bool
    {
        return true;
    }

    public function disconnect(int $fd, int $code = 1000, string $reason = ''): bool
    {
        $this->disconnected[] = ['fd' => $fd, 'code' => $code, 'reason' => $reason];

        return true;
    }
}

final class FakeSwooleWsRequest
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $query
     */
    public function __construct(
        public int $fd = 5,
        public array $server = [],
        public array $headers = [],
        public array $query = [],
    ) {
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'header' => $this->headers,
            'get' => $this->query,
            default => null,
        };
    }
}

class SwooleWebSocketTest extends TestCase
{
    private function adapter(): SwooleServerAdapter
    {
        return new SwooleServerAdapter(logLevel: 'none');
    }

    #[Test]
    public function messages_are_sent_with_the_right_opcode(): void
    {
        $server = new FakeSwooleWsServer();
        $ws = new SwooleWebSocket($server, 7, '/ws', [], [], []);

        $ws->send('bonjour');
        $ws->send_json(['ok' => true]);
        $ws->send_bytes("\x00\x01");

        $this->assertSame('bonjour', $server->pushed[0]['data']);
        $this->assertSame(0x1, $server->pushed[0]['opcode']);
        $this->assertSame('{"ok":true}', $server->pushed[1]['data']);
        $this->assertSame("\x00\x01", $server->pushed[2]['data']);
        $this->assertSame(0x2, $server->pushed[2]['opcode']);
    }

    #[Test]
    public function incoming_messages_are_consumed_with_receive(): void
    {
        $ws = new SwooleWebSocket(new FakeSwooleWsServer(), 1, '/ws', [], [], []);

        $ws->pushMessage('premier');
        $ws->pushFragment('bon', false);
        $ws->pushFragment('jour', true);

        $this->assertSame('premier', $ws->receive());
        $this->assertSame('bonjour', $ws->receive());
    }

    #[Test]
    public function json_messages_are_decoded(): void
    {
        $ws = new SwooleWebSocket(new FakeSwooleWsServer(), 1, '/ws', [], [], []);

        $ws->pushMessage('{"nom":"Oumar"}');

        $this->assertSame(['nom' => 'Oumar'], $ws->receive_json());
    }

    #[Test]
    public function closing_notifies_the_handler(): void
    {
        $ws = new SwooleWebSocket(new FakeSwooleWsServer(), 1, '/ws', [], [], []);

        $ws->pushDisconnect(new WebSocketDisconnect(1000, 'bye'));

        $this->expectException(WebSocketDisconnect::class);
        $ws->receive();
    }

    #[Test]
    public function close_reaches_the_swoole_server(): void
    {
        $server = new FakeSwooleWsServer();
        $ws = new SwooleWebSocket($server, 9, '/ws', [], [], []);

        $ws->close(1001, 'départ');

        $this->assertSame(WebSocketState::CLOSED, $ws->state());
        $this->assertSame([[9, 1001, 'départ']], array_map(
            static fn (array $entry): array => [$entry['fd'], $entry['code'], $entry['reason']],
            $server->disconnected
        ));
    }

    #[Test]
    public function the_scope_is_exposed(): void
    {
        $ws = new SwooleWebSocket(
            new FakeSwooleWsServer(),
            3,
            '/ws/rooms/salon',
            ['upgrade' => 'websocket'],
            ['x' => '1'],
            ['room' => 'salon'],
        );

        $this->assertSame('websocket', $ws->type());
        $this->assertSame('/ws/rooms/salon', $ws->path());
        $this->assertSame('1', $ws->query()['x']);
        $this->assertSame('salon', $ws->params()['room']);
        $this->assertSame(WebSocketState::OPEN, $ws->state());
    }

    #[Test]
    public function opening_a_websocket_route_runs_the_handler(): void
    {
        $app = new EorbahAPI('WS');
        $app->websocket('/ws/rooms/{room}', static function (WebSocketInterface $ws): void {
            $ws->send('salut ' . $ws->params()['room']);
        });

        $server = new FakeSwooleWsServer();
        $request = new FakeSwooleWsRequest(
            server: ['request_uri' => '/ws/rooms/salon?x=1'],
            headers: ['upgrade' => 'websocket'],
            query: ['x' => '1'],
        );

        $connections = [];
        $this->adapter()->handleOpen($server, $request, $app, $connections);

        $this->assertSame('salut salon', $server->pushed[0]['data']);
        $this->assertArrayHasKey(5, $connections);
        $this->assertSame(1000, $server->disconnected[0]['code']);
    }

    #[Test]
    public function an_unknown_websocket_route_is_rejected(): void
    {
        $app = new EorbahAPI('WS');
        $app->websocket('/ws/rooms/{room}', static fn () => null);

        $server = new FakeSwooleWsServer();
        $request = new FakeSwooleWsRequest(server: ['request_uri' => '/inconnu']);

        $connections = [];
        $this->adapter()->handleOpen($server, $request, $app, $connections);

        $this->assertSame(1008, $server->disconnected[0]['code']);
    }

    #[Test]
    public function received_frames_reach_the_connection(): void
    {
        $server = new FakeSwooleWsServer();
        $ws = new SwooleWebSocket($server, 12, '/ws', [], [], []);

        $connections = [12 => $ws];

        $this->adapter()->handleMessage(
            (object) ['fd' => 12, 'data' => 'bonjour', 'finish' => true],
            $connections
        );

        $this->assertSame('bonjour', $ws->receive());
    }

    #[Test]
    public function websocket_logs_look_like_uvicorn(): void
    {
        $messages = [];

        $adapter = new SwooleServerAdapter(logger: static function (string $level, string $message) use (&$messages): void {
            $messages[] = [$level, $message];
        });

        $app = new EorbahAPI('WS');
        $app->websocket('/ws/rooms/{room}', static fn () => null);

        $request = new FakeSwooleWsRequest(
            server: [
                'request_uri' => '/ws/rooms/salon',
                'remote_addr' => '127.0.0.1',
                'remote_port' => '53212',
            ],
        );

        $connections = [];
        $adapter->handleOpen(new FakeSwooleWsServer(), $request, $app, $connections);
        $adapter->handleClose(5, $connections);

        $texts = array_map(static fn (array $entry): string => $entry[1], $messages);

        $this->assertContains('(\'127.0.0.1\', 53212) - "WebSocket /ws/rooms/salon" [accepted]', $texts);
        $this->assertContains('connection open', $texts);
        $this->assertContains('connection closed', $texts);
    }

    #[Test]
    public function rejected_websocket_connections_are_logged(): void
    {
        $messages = [];

        $adapter = new SwooleServerAdapter(logger: static function (string $level, string $message) use (&$messages): void {
            $messages[] = [$level, $message];
        });

        $app = new EorbahAPI('WS');
        $app->websocket('/ws/rooms/{room}', static fn () => null);

        $request = new FakeSwooleWsRequest(
            server: ['request_uri' => '/inconnu', 'remote_addr' => '10.0.0.1', 'remote_port' => '1'],
        );

        $connections = [];
        $adapter->handleOpen(new FakeSwooleWsServer(), $request, $app, $connections);

        $this->assertSame('warning', $messages[0][0]);
        $this->assertStringContainsString('[rejected]', $messages[0][1]);
    }

    #[Test]
    public function closing_a_connection_notifies_the_handler(): void
    {
        $ws = new SwooleWebSocket(new FakeSwooleWsServer(), 15, '/ws', [], [], []);
        $connections = [15 => $ws];

        $this->adapter()->handleClose(15, $connections);

        $this->assertArrayNotHasKey(15, $connections);

        $this->expectException(WebSocketDisconnect::class);
        $ws->receive();
    }
}
