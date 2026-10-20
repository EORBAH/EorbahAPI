<?php

namespace Eorbahapi\Tests;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Body;
use Eorbahapi\Params\Header;
use Eorbahapi\Params\Path;
use Eorbahapi\Params\Query;
use Eorbahapi\Request;
use Eorbahapi\Response;
use Eorbahapi\Responses\JSONResponse;
use Eorbahapi\Testing\TestClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;

/**
 * Modèle Svinx utilisé pour les corps de requête.
 */
class TestUser extends BaseModel
{
    #[Field(min_length: 2, max_length: 20)]
    public string $name;

    public string $email;
}

class TestClientTest extends TestCase
{
    private function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        $app->get('/items/{id}', function (#[Path(ge: 1)] int $id, #[Query(ge: 1, le: 100)] int $limit = 10) {
            return ['id' => $id, 'limit' => $limit];
        });

        $app->post('/users', function (#[Body] TestUser $user) {
            return new JSONResponse(['user' => $user->model_dump()], 201);
        });

        $app->get('/headers', function (#[Header('X-Token')] string $token) {
            return ['token' => $token];
        });

        $app->get('/cookies', function (#[Query] ?string $unused = null) {
            return ['session' => $_COOKIE['session'] ?? null];
        });

        $app->post('/form', function (#[Query] ?string $unused = null) {
            return ['posted' => $_POST];
        });

        return $app;
    }

    #[Test]
    public function get_route_receives_path_and_query_parameters(): void
    {
        $client = new TestClient($this->app());

        $response = $client->get('/items/7?limit=3');

        $response->assertStatus(200);
        $this->assertSame(['id' => 7, 'limit' => 3], $response->json());
    }

    #[Test]
    public function invalid_query_parameter_yields_a_422_with_details(): void
    {
        $client = new TestClient($this->app());

        $response = $client->get('/items/7?limit=0');

        $response->assertStatus(422);
        $payload = $response->json();

        $this->assertTrue($payload['error']);
        $this->assertSame(['query', 'limit'], $payload['details'][0]['loc']);
        $this->assertSame('value_error', $payload['details'][0]['type']);
    }

    #[Test]
    public function post_json_body_is_validated_by_the_model(): void
    {
        $client = new TestClient($this->app());

        $response = $client->post('/users', [
            'json' => ['name' => 'Jean', 'email' => 'jean@example.com'],
        ]);

        $response->assertStatus(201);
        $this->assertSame(['name' => 'Jean', 'email' => 'jean@example.com'], $response->json()['user']);
    }

    #[Test]
    public function invalid_json_body_yields_a_422(): void
    {
        $client = new TestClient($this->app());

        $response = $client->post('/users', ['json' => ['name' => 'J', 'email' => 'nope']]);

        $response->assertStatus(422);
        $this->assertSame('body', $response->json()['details'][0]['loc'][0]);
    }

    #[Test]
    public function request_headers_are_available_to_the_handler(): void
    {
        $client = new TestClient($this->app());

        $response = $client->get('/headers', ['headers' => ['x-token' => 'abc']]);

        $this->assertSame(['token' => 'abc'], $response->json());
    }

    #[Test]
    public function unknown_route_returns_404(): void
    {
        $response = (new TestClient($this->app()))->get('/nope');

        $this->assertSame(404, $response->status());
        $this->assertSame('Not Found', $response->text());
    }

    #[Test]
    public function each_request_starts_from_a_clean_response(): void
    {
        $app = new EorbahAPI();
        $app->get('/error', fn () => new Response('boom', 500));
        $app->get('/ok', fn () => 'ok');

        $client = new TestClient($app);

        $client->get('/error')->assertStatus(500);

        $ok = $client->get('/ok');
        $ok->assertStatus(200);
        $this->assertSame('ok', $ok->text());
    }

    #[Test]
    public function handle_returns_the_response_without_sending_it(): void
    {
        $app = new EorbahAPI();
        $app->get('/hello', fn () => 'bonjour');

        ob_start();
        $response = $app->handle(new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/hello',
        ]));
        $echoed = ob_get_clean();

        $this->assertSame('', $echoed);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('bonjour', $response->body);
    }

    #[Test]
    public function run_sends_the_response(): void
    {
        $app = new EorbahAPI();
        $app->get('/hello', fn () => 'bonjour');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/hello';

        ob_start();
        $app->run();
        $echoed = ob_get_clean();

        $this->assertSame('bonjour', $echoed);
    }

    #[Test]
    public function mounted_application_receives_the_stripped_uri(): void
    {
        $sub = new EorbahAPI();
        $sub->get('/ping', fn () => ['pong' => true]);

        $app = new EorbahAPI();
        $app->mount('/admin', $sub);

        $response = (new TestClient($app))->get('/admin/ping');

        $response->assertStatus(200);
        $this->assertSame(['pong' => true], $response->json());
    }

    #[Test]
    public function default_route_handler_is_used_for_unmatched_requests(): void
    {
        $app = new EorbahAPI();
        $app->default_route(404, fn () => new JSONResponse(['missing' => true], 404));

        $response = (new TestClient($app))->get('/nope');

        $response->assertStatus(404);
        $this->assertSame(['missing' => true], $response->json());
    }

    #[Test]
    public function global_middleware_headers_are_kept_on_handler_responses(): void
    {
        $app = new EorbahAPI();
        $app->add_middleware(new \Eorbahapi\Middlewares\CORSMiddleware(allow_origins: ['*']));
        $app->get('/json', fn () => new JSONResponse(['a' => 1]));

        $response = (new TestClient($app))->get('/json', ['headers' => ['Origin' => 'https://example.com']]);

        $this->assertSame(200, $response->status());
        $this->assertSame('*', $response->header('Access-Control-Allow-Origin'));
        $this->assertSame(['a' => 1], $response->json());
    }

    #[Test]
    public function a_registered_service_is_injected_by_handle(): void
    {
        $app = new EorbahAPI();
        $service = new AutowiredService();
        $app->register(AutowiredService::class, $service);

        // handle() réassocie la requête sans perdre le conteneur.
        (new TestClient($app))->get('/whatever');

        $app->get('/service', fn (AutowiredService $injected) => ['same' => $injected === $service]);

        $this->assertSame(
            ['same' => true],
            (new TestClient($app))->get('/service')->json()
        );
    }
}
