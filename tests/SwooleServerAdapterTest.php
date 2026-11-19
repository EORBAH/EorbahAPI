<?php

namespace Eorbahapi\Tests;

use Eorbahapi\Adapter\Server\SwooleServerAdapter;
use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Body;
use Eorbahapi\Params\Path;
use Eorbahapi\Params\Query;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Requête factice reproduisant la forme de `Swoole\Http\Request`.
 */
final class FakeSwooleRequest
{
    /**
     * @param array<string, mixed> $server
     * @param array<string, string> $header
     * @param array<string, mixed> $get
     */
    public function __construct(
        public array $server = [],
        public array $header = [],
        public array $get = [],
        public array $post = [],
        public array $cookie = [],
        public array $files = [],
        private string $rawBody = '',
    ) {
    }

    public function rawContent(): string
    {
        return $this->rawBody;
    }
}

/**
 * Réponse factice reproduisant `Swoole\Http\Response`.
 */
final class FakeSwooleResponse
{
    public int $status = 0;

    /** @var array<string, string|array<int, string>> */
    public array $headers = [];

    /** @var list<string> */
    public array $chunks = [];

    /** @var list<array{name: string, value: string, options: array<int, mixed>}> */
    public array $cookies = [];

    public ?string $body = null;

    public bool $ended = false;

    public function status(int $status): void
    {
        $this->status = $status;
    }

    public function header(string $name, string|array $value): void
    {
        $this->headers[$name] = $value;
    }

    public function cookie(
        string $name,
        string $value = '',
        int $expires = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httponly = false,
        string $samesite = '',
    ): void {
        $this->cookies[] = [
            'name' => $name,
            'value' => $value,
            'options' => [$expires, $path, $domain, $secure, $httponly, $samesite],
        ];
    }

    public function write(string $chunk): void
    {
        $this->chunks[] = $chunk;
    }

    public function end(?string $body = null): void
    {
        $this->body = $body;
        $this->ended = true;
    }
}

class SwooleServerAdapterTest extends TestCase
{
    private function adapter(): SwooleServerAdapter
    {
        return new SwooleServerAdapter(logLevel: 'none');
    }

    #[Test]
    public function the_extension_is_required_to_start(): void
    {
        if (extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole est installé.');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('swoole');

        (new SwooleServerAdapter())->run(new EorbahAPI());
    }

    #[Test]
    public function swoole_requests_are_normalized_like_server_variables(): void
    {
        $request = new FakeSwooleRequest(
            server: [
                'request_method' => 'POST',
                'request_uri' => '/items/7?x=1',
                'remote_addr' => '127.0.0.1',
                'server_port' => '8000',
            ],
            header: ['content-type' => 'application/json'],
            get: ['x' => '1'],
            rawBody: '{"a":1}',
        );

        $concrete = SwooleServerAdapter::buildRequest($request);

        $this->assertSame('POST', $concrete->method());
        $this->assertSame('/items/7?x=1', $concrete->uri());
        $this->assertSame('/items/7', $concrete->path());
        $this->assertSame('1', $concrete->query('x'));
        $this->assertSame(['a' => 1], $concrete->body());
        $this->assertSame('127.0.0.1', $concrete->server('REMOTE_ADDR'));
        $this->assertSame('application/json', $concrete->getHeader('content-type'));
    }

    #[Test]
    public function a_swoole_request_reaches_the_router(): void
    {
        $app = new EorbahAPI();

        $app->post('/items/{id}', function (
            #[Path] int $id,
            #[Query] ?string $x = null,
            #[Body] array $payload,
        ) {
            return ['id' => $id, 'x' => $x, 'payload' => $payload];
        });

        $request = new FakeSwooleRequest(
            server: ['request_method' => 'POST', 'request_uri' => '/items/7?x=1'],
            header: ['content-type' => 'application/json'],
            get: ['x' => '1'],
            rawBody: '{"a":1}',
        );

        $response = new FakeSwooleResponse();

        $this->adapter()->handleRequest($request, $response, $app);

        $this->assertSame(200, $response->status);
        $this->assertSame(
            ['id' => 7, 'x' => '1', 'payload' => ['a' => 1]],
            json_decode((string) $response->body, true)
        );
        $this->assertSame('application/json', $response->headers['Content-Type'] ?? null);
    }

    #[Test]
    public function access_logs_look_like_uvicorn(): void
    {
        $messages = [];
        $adapter = new SwooleServerAdapter(logger: static function (string $level, string $message) use (&$messages): void {
            $messages[] = [$level, $message];
        });

        $app = new EorbahAPI();
        $app->get('/health', fn () => ['status' => 'ok']);

        $request = new FakeSwooleRequest(
            server: [
                'request_method' => 'GET',
                'request_uri' => '/health',
                'remote_addr' => '127.0.0.1',
                'remote_port' => '53211',
            ],
        );

        $adapter->handleRequest($request, new FakeSwooleResponse(), $app);

        $this->assertSame(
            [['info', '(\'127.0.0.1\', 53211) - "GET /health HTTP/1.1" 200 OK']],
            $messages
        );
    }

    #[Test]
    public function logging_can_be_disabled(): void
    {
        $messages = [];
        $adapter = new SwooleServerAdapter(
            logLevel: 'none',
            logger: static function (string $level, string $message) use (&$messages): void {
                $messages[] = [$level, $message];
            },
        );

        $app = new EorbahAPI();
        $app->get('/health', fn () => ['status' => 'ok']);

        $adapter->handleRequest(new FakeSwooleRequest(server: [
            'request_method' => 'GET',
            'request_uri' => '/health',
        ]), new FakeSwooleResponse(), $app);

        $this->assertSame([], $messages);
    }

    #[Test]
    public function an_unknown_route_returns_404(): void
    {
        $app = new EorbahAPI();

        $response = new FakeSwooleResponse();
        $request = new FakeSwooleRequest(server: [
            'request_method' => 'GET',
            'request_uri' => '/inconnu',
        ]);

        $this->adapter()->handleRequest($request, $response, $app);

        $this->assertSame(404, $response->status);
        $this->assertSame('Not Found', $response->body);
    }

    #[Test]
    public function cookies_are_forwarded_to_swoole(): void
    {
        $app = new EorbahAPI();
        $app->get('/login', function (\Eorbahapi\Response $response) {
            $response->cookie('session', 'abc123', ['secure' => false]);

            return ['ok' => true];
        });

        $response = new FakeSwooleResponse();
        $request = new FakeSwooleRequest(server: [
            'request_method' => 'GET',
            'request_uri' => '/login',
        ]);

        $this->adapter()->handleRequest($request, $response, $app);

        $this->assertSame(200, $response->status);
        $this->assertCount(1, $response->cookies);
        $this->assertSame('session', $response->cookies[0]['name']);
        $this->assertSame('abc123', $response->cookies[0]['value']);
        $this->assertFalse($response->cookies[0]['options'][3]); // secure=false
        $this->assertTrue($response->cookies[0]['options'][4]);  // httponly=true
    }

    #[Test]
    public function streaming_responses_are_written_chunk_by_chunk(): void
    {
        $app = new EorbahAPI();
        $app->get('/stream', static fn () => new \Eorbahapi\Responses\StreamingResponse(
            (static function (): \Generator {
                yield 'a';
                yield 'b';
                yield 'c';
            })()
        ));

        $response = new FakeSwooleResponse();
        $request = new FakeSwooleRequest(server: [
            'request_method' => 'GET',
            'request_uri' => '/stream',
        ]);

        $this->adapter()->handleRequest($request, $response, $app);

        $this->assertSame(['a', 'b', 'c'], $response->chunks);
        $this->assertTrue($response->ended);
        $this->assertNull($response->body);
    }

    #[Test]
    public function head_requests_have_no_body(): void
    {
        $app = new EorbahAPI();
        $app->get('/page', fn () => ['ok' => true]);

        $response = new FakeSwooleResponse();
        $request = new FakeSwooleRequest(server: [
            'request_method' => 'HEAD',
            'request_uri' => '/page',
        ]);

        $this->adapter()->handleRequest($request, $response, $app);

        $this->assertSame(404, $response->status); // HEAD n'est pas une route GET déclarée
        $this->assertTrue($response->ended);
    }

    #[Test]
    public function a_handler_error_becomes_a_json_500(): void
    {
        $app = new EorbahAPI();
        $app->get('/boom', static function (): never {
            throw new \RuntimeException('boom');
        });

        $response = new FakeSwooleResponse();
        $request = new FakeSwooleRequest(server: [
            'request_method' => 'GET',
            'request_uri' => '/boom',
        ]);

        $this->adapter()->handleRequest($request, $response, $app);

        $this->assertSame(500, $response->status);
        $this->assertIsString($response->body);
        $this->assertStringContainsString('Internal Server Error', $response->body);
    }
}
