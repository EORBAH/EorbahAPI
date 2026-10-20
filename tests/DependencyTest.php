<?php

namespace Eorbahapi\Tests;

use Eorbahapi\Dependency\DependencyInterface;
use Eorbahapi\EorbahAPI;
use Eorbahapi\Exceptions\DependencyScopeError;
use Eorbahapi\Params\Depends;
use Eorbahapi\Request;
use Eorbahapi\Response;
use Eorbahapi\Testing\TestClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Dépendance implémentant DependencyInterface (reçoit Request et Response).
 */
class RequestAwareDependency implements DependencyInterface
{
    public function resolve(Request $request, Response $response): mixed
    {
        return 'aware:' . $request->path();
    }
}

/**
 * Dépendance instanciée avec des arguments.
 */
class ConfiguredDependency
{
    public function __construct(
        public string $name = 'default',
        public int $limit = 10,
    ) {}

    public static function describe(): string
    {
        return 'described';
    }
}

/**
 * Service utilisé pour l'injection automatique.
 */
class AutowiredDependency
{
    public string $value = 'autowired';
}

function eorbah_dependency_ping(string $suffix = 'pong'): string
{
    return 'ping-' . $suffix;
}

class DependencyTest extends TestCase
{
    #[Test]
    public function depends_injects_a_dependency_interface_implementation(): void
    {
        $app = new EorbahAPI();
        // Une dépendance `DependencyInterface` est résolue : la valeur injectée
        // est le retour de resolve($request, $response).
        $app->get('/dep', fn (#[Depends(RequestAwareDependency::class)] string $value) => ['value' => $value]);

        $response = (new TestClient($app))->get('/dep');

        $this->assertSame(['value' => 'aware:/dep'], $response->json());
    }

    #[Test]
    public function depends_passes_constructor_arguments(): void
    {
        $app = new EorbahAPI();
        $app->get('/dep', function (
            #[Depends(ConfiguredDependency::class, args: ['name' => 'custom', 'limit' => 5])] ConfiguredDependency $dep
        ) {
            return ['name' => $dep->name, 'limit' => $dep->limit];
        });

        $this->assertSame(['name' => 'custom', 'limit' => 5], (new TestClient($app))->get('/dep')->json());
    }

    #[Test]
    public function depends_supports_a_function_name(): void
    {
        $app = new EorbahAPI();
        $app->get('/dep', fn (#[Depends('\Eorbahapi\Tests\eorbah_dependency_ping')] string $value) => $value);

        $this->assertSame('ping-pong', (new TestClient($app))->get('/dep')->text());
    }

    #[Test]
    public function depends_supports_a_callable_array(): void
    {
        $app = new EorbahAPI();
        $app->get('/dep', fn (#[Depends([ConfiguredDependency::class, 'describe'])] string $value) => $value);

        $this->assertSame('described', (new TestClient($app))->get('/dep')->text());
    }

    #[Test]
    public function a_service_registered_in_the_container_is_injected(): void
    {
        $app = new EorbahAPI();
        $service = new AutowiredDependency();
        $app->register(AutowiredDependency::class, $service);

        $app->get('/service', fn (AutowiredDependency $injected) => ['same' => $injected === $service]);

        $this->assertSame(['same' => true], (new TestClient($app))->get('/service')->json());
    }

    #[Test]
    public function classes_without_required_constructor_are_autowired(): void
    {
        $app = new EorbahAPI();
        $app->get('/autowire', fn (AutowiredDependency $dep) => $dep->value);

        $this->assertSame('autowired', (new TestClient($app))->get('/autowire')->text());
    }

    #[Test]
    public function an_unresolvable_parameter_raises_a_dependency_scope_error(): void
    {
        $app = new EorbahAPI();
        $app->get('/fail', function (string $something) {
            return $something;
        });

        $response = (new TestClient($app))->get('/fail');

        $this->assertSame(500, $response->status());
    }

    #[Test]
    public function request_and_response_are_injected_by_type(): void
    {
        $app = new EorbahAPI();
        $app->get('/io', fn (Request $request, Response $response) => [
            'path' => $request->path(),
            'status' => $response->status(),
        ]);

        $this->assertSame(['path' => '/io', 'status' => 200], (new TestClient($app))->get('/io')->json());
    }

    #[Test]
    public function defaults_are_used_when_nothing_can_be_resolved(): void
    {
        $app = new EorbahAPI();
        $app->get('/default', fn (string $value = 'fallback') => $value);

        $this->assertSame('fallback', (new TestClient($app))->get('/default')->text());
    }
}
