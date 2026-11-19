<?php

namespace Eorbahapi\Tests;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Middlewares\MiddlewareInterface;
use Eorbahapi\Request;
use Eorbahapi\Response;
use Eorbahapi\Responses\JSONResponse;
use Eorbahapi\Testing\TestClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Middleware traçant l'ordre d'exécution.
 */
class TraceMiddleware implements MiddlewareInterface
{
    public static array $trace = [];

    public function __construct(public string $name = 'mw') {}

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        self::$trace[] = 'before-' . $this->name;
        $result = $next();
        self::$trace[] = 'after-' . $this->name;

        return $result;
    }
}

/**
 * Middleware au style « process » (comme les middlewares historiques).
 */
class ProcessStyleMiddleware
{
    public static bool $called = false;

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        self::$called = true;
        $response->headers['X-Processed'] = 'yes';

        return $next();
    }
}

/**
 * Middleware qui court-circuite la route.
 */
class StopMiddleware
{
    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        return new JSONResponse(['stopped' => true], 409);
    }
}

/**
 * Classe de réponse personnalisée : enveloppe JSON `{data: …}`.
 */
class EnvelopeResponse extends Response
{
    public function __construct(mixed $data, int $status = 200, array $headers = [])
    {
        $headers['Content-Type'] ??= 'application/vnd.api+json';

        parent::__construct(json_encode(['data' => $data]), $status, $headers);
    }
}

class ApplicationTest extends TestCase
{
    protected function setUp(): void
    {
        TraceMiddleware::$trace = [];
        ProcessStyleMiddleware::$called = false;
    }

    #[Test]
    public function every_http_verb_is_routable(): void
    {
        $app = new EorbahAPI();
        $app->get('/r', fn () => 'GET');
        $app->post('/r', fn () => 'POST');
        $app->put('/r', fn () => 'PUT');
        $app->patch('/r', fn () => 'PATCH');
        $app->delete('/r', fn () => 'DELETE');
        $app->head('/r', fn () => 'HEAD');
        $app->options('/r', fn () => 'OPTIONS');

        $client = new TestClient($app);

        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'] as $method) {
            $this->assertSame($method, $client->request($method, '/r')->text(), $method);
        }
    }

    #[Test]
    public function add_api_route_accepts_pipe_separated_methods(): void
    {
        $app = new EorbahAPI();
        $app->add_api_route('/multi', 'GET|POST', fn () => 'ok');

        $client = new TestClient($app);

        $this->assertSame('ok', $client->get('/multi')->text());
        $this->assertSame('ok', $client->post('/multi', ['json' => []])->text());
        $this->assertSame(404, $client->delete('/multi')->status());
    }

    #[Test]
    public function a_static_route_takes_priority_over_a_dynamic_one(): void
    {
        $app = new EorbahAPI();
        $app->get('/items/{id}', fn (Request $request) => 'dynamic:' . $request->param('id'));
        $app->get('/items/new', fn () => 'static');

        $client = new TestClient($app);

        $this->assertSame('static', $client->get('/items/new')->text());
        $this->assertSame('dynamic:7', $client->get('/items/7')->text());
    }

    #[Test]
    public function a_default_route_handler_handles_unmatched_requests(): void
    {
        $app = new EorbahAPI();
        $app->default_route(404, fn () => ['missing' => true]);

        $response = (new TestClient($app))->get('/nope');

        $this->assertSame(404, $response->status());
        $this->assertSame(['missing' => true], $response->json());
    }

    #[Test]
    public function a_default_status_can_be_configured_without_handler(): void
    {
        $app = new EorbahAPI();
        $app->default_route(405);

        $response = (new TestClient($app))->get('/nope');

        $this->assertSame(405, $response->status());
    }

    #[Test]
    public function global_middlewares_are_applied_in_registration_order(): void
    {
        $app = new EorbahAPI();
        $app->add_middleware(new TraceMiddleware('a'));
        $app->add_middleware(new TraceMiddleware('b'));
        $app->get('/traced', fn () => 'ok');

        (new TestClient($app))->get('/traced');

        $this->assertSame(
            ['before-a', 'before-b', 'after-b', 'after-a'],
            TraceMiddleware::$trace
        );
    }

    #[Test]
    public function middlewares_receive_their_constructor_arguments(): void
    {
        $app = new EorbahAPI();
        $app->add_middleware(TraceMiddleware::class, ['name' => 'configured']);
        $app->get('/traced', fn () => 'ok');

        (new TestClient($app))->get('/traced');

        $this->assertSame(['before-configured', 'after-configured'], TraceMiddleware::$trace);
    }

    #[Test]
    public function process_style_middlewares_are_supported(): void
    {
        $app = new EorbahAPI();
        $app->add_middleware(ProcessStyleMiddleware::class);
        $app->get('/processed', fn () => 'ok');

        $response = (new TestClient($app))->get('/processed');

        $this->assertTrue(ProcessStyleMiddleware::$called);
        $this->assertSame('yes', $response->header('X-Processed'));
    }

    #[Test]
    public function a_middleware_can_short_circuit_the_pipeline(): void
    {
        $app = new EorbahAPI();
        $app->add_middleware(StopMiddleware::class);
        $app->get('/guarded', fn () => 'jamais exécuté');

        $response = (new TestClient($app))->get('/guarded');

        $this->assertSame(409, $response->status());
        $this->assertSame(['stopped' => true], $response->json());
    }

    #[Test]
    public function a_redirect_string_is_converted_into_a_redirect_response(): void
    {
        $app = new EorbahAPI();
        $app->get('/old', fn () => 'redirect:301:/new');

        $response = (new TestClient($app))->get('/old');

        $this->assertSame(301, $response->status());
        $this->assertSame('/new', $response->header('Location'));
    }

    #[Test]
    public function the_default_response_class_is_json_response(): void
    {
        $app = new EorbahAPI();

        $this->assertSame(JSONResponse::class, $app->response_class);

        $app->get('/data', fn () => ['ok' => true]);
        $app->get('/text', fn () => 'bonjour');

        $response = (new TestClient($app))->get('/data');

        $this->assertSame('application/json', $response->header('Content-Type'));
        $this->assertSame(['ok' => true], $response->json());

        // Un scalaire reste un corps texte, même avec JSONResponse par défaut.
        $this->assertSame('bonjour', (new TestClient($app))->get('/text')->text());
    }

    #[Test]
    public function the_response_class_can_be_customized(): void
    {
        $app = new EorbahAPI(default_response_class: EnvelopeResponse::class);
        $app->get('/data', fn () => ['ok' => true]);

        $response = (new TestClient($app))->get('/data');

        $this->assertSame(EnvelopeResponse::class, $app->response_class);
        $this->assertSame('application/vnd.api+json', $response->header('Content-Type'));
        $this->assertSame(['data' => ['ok' => true]], $response->json());
    }

    #[Test]
    public function the_response_class_can_be_changed_after_construction(): void
    {
        $app = new EorbahAPI();
        $app->set_response_class(EnvelopeResponse::class);
        $app->get('/data', fn () => ['ok' => true]);

        $response = (new TestClient($app))->get('/data');

        $this->assertSame(EnvelopeResponse::class, $app->response_class);
        $this->assertSame(['data' => ['ok' => true]], $response->json());
    }

    #[Test]
    public function the_response_class_renders_default_route_payloads_too(): void
    {
        $app = new EorbahAPI(default_response_class: EnvelopeResponse::class);
        $app->default_route(418, fn () => ['detail' => 'théière']);

        $response = (new TestClient($app))->get('/absent');

        $this->assertSame(418, $response->status());
        $this->assertSame(['data' => ['detail' => 'théière']], $response->json());
    }

    #[Test]
    public function a_response_instance_bypasses_the_response_class(): void
    {
        $app = new EorbahAPI(default_response_class: EnvelopeResponse::class);
        $app->get('/custom', fn () => new JSONResponse(['x' => 1], 201));

        $response = (new TestClient($app))->get('/custom');

        $this->assertSame(201, $response->status());
        $this->assertSame(['x' => 1], $response->json());
    }

    #[Test]
    public function an_invalid_response_class_is_rejected(): void
    {
        $this->expectException(\Eorbahapi\Exceptions\EorbahAPIError::class);

        new EorbahAPI(default_response_class: \stdClass::class);
    }

    #[Test]
    public function nested_applications_can_be_mounted(): void
    {
        $inner = new EorbahAPI();
        $inner->get('/ping', fn () => ['level' => 'inner']);

        $middle = new EorbahAPI();
        $middle->mount('/inner', $inner);

        $app = new EorbahAPI();
        $app->mount('/middle', $middle);

        $response = (new TestClient($app))->get('/middle/inner/ping');

        $this->assertSame(['level' => 'inner'], $response->json());
    }

    #[Test]
    public function run_sends_the_response_of_an_injected_request(): void
    {
        $app = new EorbahAPI();
        $app->get('/hello', fn () => 'bonjour');

        ob_start();
        $app->run(new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/hello']));
        $output = ob_get_clean();

        $this->assertSame('bonjour', $output);
    }

    #[Test]
    public function metadata_is_exposed(): void
    {
        $app = new EorbahAPI('Mon API', 'Description', true);

        $this->assertSame('Mon API', $app->title);
        $this->assertSame('Description', $app->description);
        $this->assertTrue($app->debug);
    }

    #[Test]
    public function handle_can_be_called_several_times(): void
    {
        $app = new EorbahAPI();
        $app->get('/count', fn () => 'ok');

        $client = new TestClient($app);

        $this->assertSame(200, $client->get('/count')->status());
        $this->assertSame(200, $client->get('/count')->status());
        $this->assertSame(404, $client->get('/absent')->status());
        $this->assertSame(200, $client->get('/count')->status());
    }

    #[Test]
    public function a_router_prefix_is_applied_to_every_route(): void
    {
        $app = new EorbahAPI(prefix: '/api');
        $app->get('/users', fn () => ['users' => []]);

        $client = new TestClient($app);

        $this->assertSame(['users' => []], $client->get('/api/users')->json());
        $this->assertSame(404, $client->get('/users')->status());
    }
}
