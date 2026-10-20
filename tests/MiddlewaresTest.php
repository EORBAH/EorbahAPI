<?php

namespace Eorbahapi\Tests;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Middlewares\CORSMiddleware;
use Eorbahapi\Middlewares\SessionMiddleware;
use Eorbahapi\Middlewares\TrustedHostMiddleware;
use Eorbahapi\Testing\TestClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MiddlewaresTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_HOST']);
    }

    #[Test]
    public function trusted_host_allows_a_known_host(): void
    {
        $app = new EorbahAPI();
        $app->add_middleware(new TrustedHostMiddleware(['example.com']));
        $app->get('/', fn () => ['ok' => true]);

        $response = (new TestClient($app))->get('/', ['headers' => ['Host' => 'example.com']]);

        $this->assertSame(200, $response->status());
        $this->assertSame(['ok' => true], $response->json());
    }

    #[Test]
    public function trusted_host_rejects_an_unknown_host(): void
    {
        $app = new EorbahAPI();
        $app->add_middleware(new TrustedHostMiddleware(['example.com']));
        $app->get('/', fn () => ['ok' => true]);

        $response = (new TestClient($app))->get('/', ['headers' => ['Host' => 'evil.test']]);

        $this->assertSame(403, $response->status());
        $this->assertStringContainsString('Untrusted host', $response->text());
    }

    #[Test]
    public function trusted_host_ignores_the_port(): void
    {
        $app = new EorbahAPI();
        $app->add_middleware(new TrustedHostMiddleware(['example.com']));
        $app->get('/', fn () => 'ok');

        $response = (new TestClient($app))->get('/', ['headers' => ['Host' => 'example.com:8080']]);

        $this->assertSame(200, $response->status());
    }

    #[Test]
    public function cors_sets_the_headers_for_an_allowed_origin(): void
    {
        $app = new EorbahAPI();
        $app->add_middleware(new CORSMiddleware(allow_origins: ['https://example.com']));
        $app->get('/data', fn () => ['ok' => true]);

        $response = (new TestClient($app))->get('/data', [
            'headers' => ['Origin' => 'https://example.com'],
        ]);

        $this->assertSame('https://example.com', $response->header('Access-Control-Allow-Origin'));
        $this->assertSame('GET, POST, PUT, DELETE, PATCH, OPTIONS', $response->header('Access-Control-Allow-Methods'));
    }

    #[Test]
    public function cors_rejects_a_foreign_origin(): void
    {
        $app = new EorbahAPI();
        $app->add_middleware(new CORSMiddleware(allow_origins: ['https://example.com']));
        $app->get('/data', fn () => ['ok' => true]);

        $response = (new TestClient($app))->get('/data', [
            'headers' => ['Origin' => 'https://evil.test'],
        ]);

        $this->assertSame(403, $response->status());
    }

    #[Test]
    public function cors_preflight_returns_204_without_hitting_the_route(): void
    {
        $app = new EorbahAPI();
        $app->add_middleware(new CORSMiddleware());
        $app->get('/data', fn () => ['ok' => true]);

        $response = (new TestClient($app))->request('OPTIONS', '/data', [
            'headers' => ['Origin' => 'https://example.com'],
        ]);

        $this->assertSame(204, $response->status());
        $this->assertSame('86400', $response->header('Access-Control-Max-Age'));
    }

    #[Test]
    public function session_middleware_keeps_the_pipeline_working(): void
    {
        $app = new EorbahAPI();
        $app->add_middleware(new SessionMiddleware(['name' => 'EORBAHSESSID']));
        $app->get('/', fn () => ['session' => session_name()]);

        $response = (new TestClient($app))->get('/');

        $this->assertSame(200, $response->status());
        $this->assertSame(['session' => 'EORBAHSESSID'], $response->json());
    }
}
