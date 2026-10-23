<?php

namespace Eorbahapi\Tests;

use Eorbahapi\APIRouter;
use Eorbahapi\EorbahAPI;
use Eorbahapi\Middlewares\MiddlewareInterface;
use Eorbahapi\Request;
use Eorbahapi\Response;
use Eorbahapi\Responses\JSONResponse;
use Eorbahapi\Routing\Attributes\Get;
use Eorbahapi\Routing\Attributes\Middleware;
use Eorbahapi\Routing\Attributes\Post;
use Eorbahapi\Routing\Attributes\Route;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Middleware de test qui enregistre ses invocations et accepte des arguments
 * nommés identiques à ceux de RateLimitingMiddleware.
 */
class RecordMiddleware implements MiddlewareInterface
{
    /** @var list<array{maxRequests: int, path: string}> */
    public static array $calls = [];

    public function __construct(
        public int $maxRequests = 100,
        public bool $routeSpecific = true,
    ) {}

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        self::$calls[] = ['maxRequests' => $this->maxRequests, 'path' => $request->path()];

        return $next();
    }
}

/**
 * Middleware qui court-circuite la route sans l'exécuter.
 */
class BlockingMiddleware implements MiddlewareInterface
{
    public static int $calls = 0;

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        self::$calls++;

        return new JSONResponse(['blocked' => true], 403);
    }
}

/**
 * Contrôleur illustrant la syntaxe demandée : #[Route]/#[Get]/#[Post] +
 * #[Middleware] séparé.
 */
class AuthController
{
    public static bool $meCalled = false;

    #[Route('/login', methods: ['GET'])]
    #[Middleware(RecordMiddleware::class, args: ['maxRequests' => 3, 'routeSpecific' => true])]
    public function login()
    {
        return ['status' => 'ok'];
    }

    #[Post('/me')]
    public function me()
    {
        self::$meCalled = true;

        return ['me' => true];
    }

    public function notARoute()
    {
        return ['nope' => true];
    }
}

/**
 * Contrôleur avec middleware au niveau de la classe.
 */
#[Middleware(RecordMiddleware::class, args: ['maxRequests' => 5])]
class ClassScopedController
{
    #[Get('/a')]
    public function a()
    {
        return ['a' => true];
    }

    #[Get('/b')]
    #[Middleware(BlockingMiddleware::class)]
    public function b()
    {
        return ['b' => true];
    }
}

/**
 * Contrôleur utilisant l'ancien namespace des attributs.
 */
class LegacyController
{
    #[Get('/legacy')]
    public function index()
    {
        return ['legacy' => true];
    }
}

class RoutingTest extends TestCase
{
    protected function setUp(): void
    {
        RecordMiddleware::$calls = [];
        BlockingMiddleware::$calls = 0;
        AuthController::$meCalled = false;
    }

    private function request(string $method, string $uri): Response
    {
        return (new EorbahAPI())->handle(new Request(
            server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri],
        ));
    }

    #[Test]
    public function middleware_attribute_runs_with_named_arguments(): void
    {
        $app = new EorbahAPI();
        $app->include_controller(new AuthController());

        $response = $app->handle(new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/login'],
        ));

        $this->assertSame(200, $response->status);
        $this->assertSame('{"status":"ok"}', $response->body);
        $this->assertSame(
            [['maxRequests' => 3, 'path' => '/login']],
            RecordMiddleware::$calls
        );
    }

    #[Test]
    public function verb_attribute_restricts_the_http_method(): void
    {
        $app = new EorbahAPI();
        $app->include_controller(new AuthController());

        $post = $app->handle(new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/me'],
        ));

        $this->assertSame(200, $post->status);
        $this->assertTrue(AuthController::$meCalled);

        // GET /me : aucune route GET n'est déclarée sur ce contrôleur.
        $get = $app->handle(new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/me'],
        ));

        $this->assertSame(404, $get->status);
    }

    #[Test]
    public function class_level_middleware_wraps_every_route(): void
    {
        $app = new EorbahAPI();
        $app->include_controller(new ClassScopedController());

        $app->handle(new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/a']));

        $this->assertSame(
            [['maxRequests' => 5, 'path' => '/a']],
            RecordMiddleware::$calls
        );
    }

    #[Test]
    public function a_method_middleware_can_short_circuit_the_route(): void
    {
        $app = new EorbahAPI();
        $app->include_controller(new ClassScopedController());

        $response = $app->handle(new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/b'],
        ));

        $this->assertSame(403, $response->status);
        $this->assertSame('{"blocked":true}', $response->body);
        $this->assertSame(1, BlockingMiddleware::$calls);
    }

    #[Test]
    public function controller_prefix_is_applied_to_every_route(): void
    {
        $app = new EorbahAPI();
        $app->include_controller(new AuthController(), prefix: '/auth');

        $app->handle(new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/auth/login']));

        $this->assertSame([['maxRequests' => 3, 'path' => '/auth/login']], RecordMiddleware::$calls);
    }

    #[Test]
    public function a_controller_class_name_can_be_passed_instead_of_an_instance(): void
    {
        $app = new EorbahAPI();
        $app->include_controller(AuthController::class);

        $response = $app->handle(new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/me'],
        ));

        $this->assertSame(200, $response->status);
    }

    #[Test]
    public function legacy_route_attributes_are_still_detected(): void
    {
        $app = new EorbahAPI();
        $app->include_controller(new LegacyController());

        $response = $app->handle(new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/legacy'],
        ));

        $this->assertSame('{"legacy":true}', $response->body);
    }

    #[Test]
    public function route_attribute_middlewares_parameter_still_works(): void
    {
        $router = new APIRouter();
        $router->add_api_route('/x', ['GET'], fn () => ['x' => 1], [RecordMiddleware::class]);

        $response = $router->dispatch(new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/x'],
        ));

        $this->assertSame('{"x":1}', $response->body);
        $this->assertSame([['maxRequests' => 100, 'path' => '/x']], RecordMiddleware::$calls);
    }

    #[Test]
    public function include_router_keeps_the_router_prefix(): void
    {
        $router = new APIRouter('/api');
        $router->get('/users', fn () => ['users' => []]);

        $app = new EorbahAPI();
        $app->include_router($router);

        $response = $app->handle(new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/users'],
        ));

        $this->assertSame('{"users":[]}', $response->body);
    }

    #[Test]
    public function router_matches_static_and_parameterized_routes(): void    {
        $router = new APIRouter('/api');
        $router->get('/users', fn () => ['users' => []]);
        $router->get('/users/{id}', fn (Request $request) => ['id' => $request->param('id')]);

        $static = $router->match('/api/users', 'GET');
        $this->assertNotNull($static);
        $this->assertSame([], $static['params']);

        $dynamic = $router->match('/api/users/42', 'GET');
        $this->assertNotNull($dynamic);
        $this->assertSame(['id' => '42'], $dynamic['params']);

        $this->assertNull($router->match('/api/users/42', 'DELETE'));
    }

    #[Test]
    public function middlewares_can_be_declared_in_several_forms(): void
    {
        $this->assertSame(
            ['class' => RecordMiddleware::class, 'args' => []],
            APIRouter::normalizeMiddleware(RecordMiddleware::class)
        );

        $this->assertSame(
            ['class' => RecordMiddleware::class, 'args' => ['maxRequests' => 3]],
            APIRouter::normalizeMiddleware([RecordMiddleware::class, ['maxRequests' => 3]])
        );

        $this->assertSame(
            ['class' => RecordMiddleware::class, 'args' => [7]],
            APIRouter::normalizeMiddleware([RecordMiddleware::class, 7])
        );

        $instance = new RecordMiddleware(9);
        $normalized = APIRouter::normalizeMiddleware($instance);
        $this->assertSame(RecordMiddleware::class, $normalized['class']);
        $this->assertSame($instance, $normalized['instance']);
    }
}
