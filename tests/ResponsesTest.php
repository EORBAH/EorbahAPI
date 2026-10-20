<?php

namespace Eorbahapi\Tests;

use Eorbahapi\Request;
use Eorbahapi\Response;
use Eorbahapi\Responses\EventStreamResponse;
use Eorbahapi\Responses\FileResponse;
use Eorbahapi\Responses\HTMLResponse;
use Eorbahapi\Responses\JSONResponse;
use Eorbahapi\Responses\RedirectResponse;
use Eorbahapi\Responses\StreamingResponse;
use Eorbahapi\Status;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResponsesTest extends TestCase
{
    #[Test]
    public function json_response_encodes_the_body_and_sets_the_content_type(): void
    {
        $response = new JSONResponse(['ok' => true], 201);

        $this->assertSame(201, $response->status);
        $this->assertSame('application/json', $response->headers['Content-Type']);
        $this->assertSame('{"ok":true}', $response->body);
        $this->assertSame('{"ok":true}', $response->body());
        $this->assertSame(201, $response->status());
    }

    #[Test]
    public function json_response_keeps_custom_headers(): void
    {
        $response = new JSONResponse(['ok' => true], 200, ['Content-Type' => 'application/vnd.api+json', 'X-Trace' => 'abc']);

        $this->assertSame('application/vnd.api+json', $response->headers['Content-Type']);
        $this->assertSame('abc', $response->headers['X-Trace']);
    }

    #[Test]
    public function html_response_sets_the_html_content_type(): void
    {
        $response = new HTMLResponse('<h1>Hello</h1>');

        $this->assertSame(200, $response->status);
        $this->assertSame('text/html; charset=UTF-8', $response->headers['Content-Type']);
        $this->assertSame('<h1>Hello</h1>', $response->body);
    }

    #[Test]
    public function redirect_response_sets_the_location_header(): void
    {
        $response = new RedirectResponse('/login', 307);

        $this->assertSame(307, $response->status);
        $this->assertSame('/login', $response->headers['Location']);
        $this->assertSame('redirect:307:/login', $response->body);
    }

    #[Test]
    public function response_helpers_expose_status_body_and_content_type(): void
    {
        $response = new Response('ok', 202);
        $response->set_content_type('json', 60);

        $this->assertSame(202, $response->status());
        $this->assertSame('ok', $response->body());
        $this->assertSame('application/json; charset=UTF-8', $response->headers['Content-Type']);
        $this->assertStringContainsString('max-age=60', $response->headers['Cache-Control']);
        $this->assertSame('ok', $response->text());
    }

    #[Test]
    public function streaming_response_sends_each_chunk(): void
    {
        $generator = (static function (): \Generator {
            yield 'a';
            yield 'b';
            yield 'c';
        })();

        $response = new StreamingResponse($generator, 200, ['Content-Type' => 'text/plain']);

        $this->expectOutputString('abc');

        $response->send();

        $this->assertSame('text/plain', $response->headers['Content-Type']);
    }

    #[Test]
    public function event_stream_response_sets_sse_headers(): void
    {
        $generator = (static function (): \Generator {
            yield "data: ping\n\n";
        })();

        $response = new EventStreamResponse($generator);

        $this->assertSame('text/event-stream', $response->headers['Content-Type']);
        $this->assertSame('no-cache', $response->headers['Cache-Control']);
        $this->assertSame('keep-alive', $response->headers['Connection']);
    }

    #[Test]
    public function file_response_is_a_response(): void
    {
        $response = new FileResponse('contenu', 200, ['Content-Type' => 'text/plain']);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('contenu', $response->body);
    }

    #[Test]
    public function status_holds_http_constants(): void
    {
        $this->assertSame(401, Status::HTTP_401_UNAUTHORIZED);
    }
}
