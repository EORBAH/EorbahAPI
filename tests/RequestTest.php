<?php

namespace Eorbahapi\Tests;

use Eorbahapi\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    private function request(array $options = []): Request
    {
        return new Request(
            body: $options['body'] ?? null,
            params: $options['params'] ?? [],
            server: $options['server'] ?? [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/items/7?limit=3',
                'QUERY_STRING' => 'limit=3',
                'REMOTE_ADDR' => '10.0.0.1',
                'HTTP_USER_AGENT' => 'phpunit',
            ],
            query: $options['query'] ?? ['limit' => '3'],
            postData: $options['post'] ?? [],
            cookies: $options['cookies'] ?? [],
            files: $options['files'] ?? [],
            headers: $options['headers'] ?? [],
        );
    }

    #[Test]
    public function it_exposes_method_uri_and_path(): void
    {
        $request = $this->request();

        $this->assertSame('GET', $request->method());
        $this->assertSame('/items/7?limit=3', $request->uri());
        $this->assertSame('/items/7', $request->path());
    }

    #[Test]
    public function it_exposes_query_parameters(): void
    {
        $request = $this->request();

        $this->assertSame(['limit' => '3'], $request->query());
        $this->assertSame('3', $request->query('limit'));
        $this->assertNull($request->query('absent'));
        $this->assertSame('defaut', $request->query('absent', 'defaut'));
        $this->assertSame('limit=3', $request->query_string());
    }

    #[Test]
    public function it_merges_route_and_query_parameters(): void
    {
        $request = $this->request(['params' => ['id' => '7']]);

        $this->assertSame('7', $request->params('id'));
        // params() fusionne la query dans les paramètres.
        $this->assertSame(['id' => '7', 'limit' => '3'], $request->params());
        $this->assertSame('7', $request->param('id'));
    }

    #[Test]
    public function it_reads_an_injected_body(): void
    {
        $request = $this->request(['body' => ['name' => 'Jean']]);

        $this->assertSame(['name' => 'Jean'], $request->body());
        $this->assertSame('Jean', $request->body('name'));
        $this->assertSame('defaut', $request->body('absent', 'defaut'));
        $this->assertSame(['name' => 'Jean'], $request->json());
    }

    #[Test]
    public function it_decodes_a_raw_json_body(): void
    {
        $request = $this->request(['body' => '{"name":"Jean"}']);

        $this->assertSame(['name' => 'Jean'], $request->body());
    }

    #[Test]
    public function it_falls_back_on_post_data_when_the_body_is_empty(): void
    {
        $request = $this->request(['body' => '', 'post' => ['name' => 'Jean']]);

        $this->assertSame(['name' => 'Jean'], $request->body());
        $this->assertSame(['name' => 'Jean'], $request->post());
        $this->assertSame(['name' => 'Jean'], $request->form());
    }

    #[Test]
    public function it_reads_headers_case_insensitively(): void
    {
        $request = $this->request(['headers' => ['X-Api-Key' => 'secret']]);

        $this->assertSame('secret', $request->getHeader('x-api-key'));
        $this->assertSame('secret', $request->header('X-API-KEY'));
        $this->assertNull($request->getHeader('X-Absent'));
    }

    #[Test]
    public function it_extracts_a_bearer_token(): void
    {
        $request = $this->request(['headers' => ['Authorization' => 'Bearer abc.def.ghi']]);

        $this->assertSame('abc.def.ghi', $request->getBearerToken());
    }

    #[Test]
    public function it_reads_cookies_and_files(): void
    {
        $request = $this->request([
            'cookies' => ['session' => 'abc'],
            'files' => ['avatar' => ['name' => 'a.png']],
        ]);

        $this->assertSame('abc', $request->cookie('session'));
        $this->assertSame(['session' => 'abc'], $request->cookie());
        $this->assertSame('a.png', $request->file('avatar')['name']);
        $this->assertNull($request->file('absent'));
    }

    #[Test]
    public function input_merges_body_post_and_query(): void
    {
        $request = $this->request([
            'body' => ['from' => 'body'],
            'post' => ['from_post' => 'post'],
        ]);

        $input = $request->input();

        $this->assertSame('body', $input['from']);
        $this->assertSame('post', $input['from_post']);
        $this->assertSame('3', $input['limit']);
        $this->assertSame('body', $request->input('from'));
    }

    #[Test]
    public function with_uri_returns_a_copy_with_another_uri(): void
    {
        $request = $this->request();
        $copy = $request->withUri('/admin/items');

        $this->assertNotSame($request, $copy);
        $this->assertSame('/admin/items', $copy->path());
        $this->assertSame('/items/7', $request->path());
    }

    #[Test]
    public function it_exposes_server_values(): void
    {
        $request = $this->request();

        $this->assertSame('10.0.0.1', $request->getClientIP());
        $this->assertSame('phpunit', $request->userAgent());
        $this->assertSame('10.0.0.1', $request->server('REMOTE_ADDR'));
        $this->assertSame('x', $request->server('ABSENT', 'x'));
    }

    #[Test]
    public function it_reads_headers_from_server_variables_when_none_are_injected(): void
    {
        $request = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_X_CUSTOM' => 'value',
        ]);

        $this->assertSame('value', $request->getHeader('X-Custom'));
        $this->assertSame('value', $request->getHeader('X_Custom'));
    }
}
