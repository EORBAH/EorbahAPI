<?php

namespace Eorbahapi\Tests;

use Eorbahapi\Datastructures\UploadFile;
use Eorbahapi\Dependency\DependencyResolver;
use Eorbahapi\Exceptions\DependencyScopeError;
use Eorbahapi\Exceptions\RequestValidationError;
use Eorbahapi\Params\Body;
use Eorbahapi\Params\Cookie;
use Eorbahapi\Params\Depends;
use Eorbahapi\Params\File;
use Eorbahapi\Params\Form;
use Eorbahapi\Params\Header;
use Eorbahapi\Params\Path;
use Eorbahapi\Params\Query;
use Eorbahapi\Params\Security;
use Eorbahapi\Request;
use Eorbahapi\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;
use Svinx\Validator\Validators\EmailStr;

/**
 * Modèle Svinx utilisé pour les tests de #[Body].
 */
class CreateUser extends BaseModel
{
    #[Field(alias: 'username', min_length: 2, max_length: 20)]
    public string $name = 'Anonyme';

    public EmailStr $email;
}

/**
 * Dépendance de test utilisée par #[Depends].
 */
function eorbah_test_dependency(string $suffix = 'default'): string
{
    return 'dep-' . $suffix;
}

/**
 * Service sans constructeur, résolu par le conteneur ou instancié à la volée.
 */
class AutowiredService
{
}

/**
 * Schéma de sécurité minimal : un callable qui produit un jeton.
 */
class FakeBearer
{
    public function __invoke(): string
    {
        return 'token-123';
    }
}

class ParamsTest extends TestCase
{
    private array $serverBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];
    }

    /**
     * Construit un resolver alimenté par les superglobales demandées.
     */
    private function resolver(
        array $server = [],
        array $get = [],
        array $post = [],
        array $cookie = [],
        array $files = [],
        ?array $body = null
    ): DependencyResolver {
        $_SERVER = array_merge($this->serverBackup, $server);
        $_GET = $get;
        $_POST = $post;
        $_COOKIE = $cookie;
        $_FILES = $files;

        return new DependencyResolver(new Request($body), new Response());
    }

    private function resolve(DependencyResolver $resolver, callable $callback, array $provided = []): array
    {
        return $resolver->resolve($callback, $provided);
    }

    // ------------------------------------------------------------------
    // Query
    // ------------------------------------------------------------------

    #[Test]
    public function query_value_is_coerced_to_the_declared_type(): void
    {
        $resolver = $this->resolver(get: ['limit' => '42']);

        $args = $this->resolve($resolver, function (#[Query] int $limit) {
            return $limit;
        });

        $this->assertSame([42], $args);
    }

    #[Test]
    public function query_constraints_come_from_svinx_field_info(): void
    {
        $resolver = $this->resolver(get: ['limit' => '0']);

        $this->expectException(RequestValidationError::class);

        $this->resolve($resolver, function (#[Query(ge: 1, le: 100)] int $limit) {
            return $limit;
        });
    }

    #[Test]
    public function query_error_carries_location_and_message(): void
    {
        $resolver = $this->resolver(get: ['email' => 'pas-un-email']);

        try {
            $this->resolve($resolver, function (#[Query] EmailStr $email) {
                return $email;
            });
            $this->fail('Une RequestValidationError était attendue.');
        } catch (RequestValidationError $error) {
            $errors = $error->getErrors();

            $this->assertSame('query', $errors[0]['loc'][0]);
            $this->assertSame('email', $errors[0]['loc'][1]);
            $this->assertSame('value_error', $errors[0]['type']);
            $this->assertStringContainsString('EmailStr', $errors[0]['msg']);
            $this->assertSame([], $error->getHeaders());
        }
    }

    #[Test]
    public function missing_required_query_parameter_is_reported_as_missing(): void
    {
        $resolver = $this->resolver();

        try {
            $this->resolve($resolver, function (#[Query] int $limit) {
                return $limit;
            });
            $this->fail('Une RequestValidationError était attendue.');
        } catch (RequestValidationError $error) {
            $errors = $error->getErrors();

            $this->assertSame('missing', $errors[0]['type']);
            $this->assertSame(['query', 'limit'], $errors[0]['loc']);
        }
    }

    #[Test]
    public function query_falls_back_on_attribute_default_then_php_default(): void
    {
        $resolver = $this->resolver();

        $this->assertSame([10], $this->resolve($resolver, function (
            #[Query(default: 10)] int $limit
        ) {
            return $limit;
        }));

        $this->assertSame([3], $this->resolve($resolver, function (
            #[Query] int $limit = 3
        ) {
            return $limit;
        }));

        $this->assertSame([null], $this->resolve($resolver, function (
            #[Query] ?int $limit = null
        ) {
            return $limit;
        }));
    }

    #[Test]
    public function query_accepts_a_custom_name_and_choices(): void
    {
        $resolver = $this->resolver(get: ['sort' => 'desc']);

        $args = $this->resolve($resolver, function (
            #[Query('sort', choices: ['asc', 'desc'])] string $order
        ) {
            return $order;
        });

        $this->assertSame(['desc'], $args);
    }

    #[Test]
    public function query_array_parameter_is_kept_as_list(): void
    {
        $resolver = $this->resolver(get: ['tags' => ['php', 'api']]);

        $this->assertSame([['php', 'api']], $this->resolve($resolver, function (
            #[Query] array $tags
        ) {
            return $tags;
        }));
    }

    // ------------------------------------------------------------------
    // Header / Cookie / Path
    // ------------------------------------------------------------------

    #[Test]
    public function header_parameter_is_read_from_server_variables(): void
    {
        $resolver = $this->resolver(server: ['HTTP_X_API_KEY' => 'secret-key']);

        $args = $this->resolve($resolver, function (#[Header('X-Api-Key')] string $key) {
            return $key;
        });

        $this->assertSame(['secret-key'], $args);
    }

    #[Test]
    public function header_underscores_are_converted_to_dashes(): void
    {
        $resolver = $this->resolver(server: ['HTTP_USER_AGENT' => 'phpunit/13']);

        $args = $this->resolve($resolver, function (#[Header] string $user_agent) {
            return $user_agent;
        });

        $this->assertSame(['phpunit/13'], $args);
    }

    #[Test]
    public function cookie_parameter_is_read_from_cookies(): void
    {
        $resolver = $this->resolver(cookie: ['session_id' => 'abc']);

        $args = $this->resolve($resolver, function (#[Cookie] string $session_id) {
            return $session_id;
        });

        $this->assertSame(['abc'], $args);
    }

    #[Test]
    public function path_parameter_is_read_from_route_parameters(): void
    {
        $resolver = $this->resolver();

        $args = $resolver->resolve(function (#[Path(ge: 1)] int $id) {
            return $id;
        }, ['id' => '7']);

        $this->assertSame([7], $args);
    }

    #[Test]
    public function path_parameter_rejects_an_invalid_value(): void
    {
        $resolver = $this->resolver();

        $this->expectException(RequestValidationError::class);

        $resolver->resolve(function (#[Path(ge: 1)] int $id) {
            return $id;
        }, ['id' => '0']);
    }

    // ------------------------------------------------------------------
    // Body / Form / File
    // ------------------------------------------------------------------

    #[Test]
    public function body_can_validate_a_whole_svinx_model(): void
    {
        $resolver = $this->resolver(body: [
            'username' => 'Jean',
            'email' => 'jean@example.com',
        ]);

        $args = $this->resolve($resolver, function (#[Body] CreateUser $user) {
            return $user;
        });

        $this->assertInstanceOf(CreateUser::class, $args[0]);
        $this->assertSame('Jean', $args[0]->name);
        $this->assertSame('jean@example.com', $args[0]->email->dump());
    }

    #[Test]
    public function body_model_validation_errors_become_request_errors(): void
    {
        $resolver = $this->resolver(body: [
            'username' => 'J',
            'email' => 'nope',
        ]);

        try {
            $this->resolve($resolver, function (#[Body] CreateUser $user) {
                return $user;
            });
            $this->fail('Une RequestValidationError était attendue.');
        } catch (RequestValidationError $error) {
            $errors = $error->getErrors();

            $this->assertSame('body', $errors[0]['loc'][0]);
            $this->assertSame('value_error', $errors[0]['type']);
        }
    }

    #[Test]
    public function body_scalar_parameter_is_extracted_from_the_payload(): void
    {
        $resolver = $this->resolver(body: ['user_name' => 'Jean']);

        $args = $this->resolve($resolver, function (
            #[Body(name: 'user_name', min_length: 2)] string $name
        ) {
            return $name;
        });

        $this->assertSame(['Jean'], $args);
    }

    #[Test]
    public function body_array_parameter_receives_the_whole_payload(): void
    {
        $resolver = $this->resolver(body: ['a' => 1, 'b' => 2]);

        $this->assertSame([['a' => 1, 'b' => 2]], $this->resolve($resolver, function (
            #[Body] array $payload
        ) {
            return $payload;
        }));
    }

    #[Test]
    public function form_parameter_is_read_from_post_data(): void
    {
        $resolver = $this->resolver(post: ['age' => '30']);

        $args = $this->resolve($resolver, function (#[Form(ge: 18, le: 130)] int $age) {
            return $age;
        });

        $this->assertSame([30], $args);
    }

    #[Test]
    public function file_parameter_builds_an_upload_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'eorbah');
        file_put_contents($path, 'contenu');

        $resolver = $this->resolver(files: [
            'avatar' => [
                'name' => 'avatar.txt',
                'type' => 'text/plain',
                'tmp_name' => $path,
                'error' => UPLOAD_ERR_OK,
                'size' => 7,
            ],
        ]);

        $args = $this->resolve($resolver, function (#[File] UploadFile $avatar) {
            return $avatar;
        });

        $this->assertInstanceOf(UploadFile::class, $args[0]);
        $this->assertSame('avatar.txt', $args[0]->filename);
        $this->assertSame('text/plain', $args[0]->content_type);
        $this->assertSame('contenu', $args[0]->read());

        $args[0]->close();
        @unlink($path);
    }

    #[Test]
    public function file_parameter_can_stay_raw(): void
    {
        $resolver = $this->resolver(files: [
            'doc' => [
                'name' => 'doc.pdf',
                'type' => 'application/pdf',
                'tmp_name' => '/tmp/doc.pdf',
                'error' => UPLOAD_ERR_OK,
                'size' => 10,
            ],
        ]);

        $args = $this->resolve($resolver, function (#[File] array $doc) {
            return $doc;
        });

        $this->assertSame('doc.pdf', $args[0]['name']);
    }

    // ------------------------------------------------------------------
    // Cohérence et garde-fous
    // ------------------------------------------------------------------

    #[Test]
    public function two_source_attributes_on_one_parameter_are_rejected(): void
    {
        $resolver = $this->resolver(get: ['q' => 'x']);

        $this->expectException(DependencyScopeError::class);

        $this->resolve($resolver, function (#[Query] #[Header] string $q) {
            return $q;
        });
    }

    #[Test]
    public function a_parameter_without_source_still_receives_route_values(): void
    {
        $resolver = $this->resolver();

        $args = $resolver->resolve(function (int $id) {
            return $id;
        }, ['id' => '9']);

        $this->assertSame([9], $args);
    }

    #[Test]
    public function request_and_response_are_injected_without_attributes(): void
    {
        $request = new Request();
        $response = new Response();
        $resolver = new DependencyResolver($request, $response);

        $args = $resolver->resolve(function (Request $req, Response $res) {
            return [$req, $res];
        });

        $this->assertSame($request, $args[0]);
        $this->assertSame($response, $args[1]);
    }

    // ------------------------------------------------------------------
    // Dépendances
    // ------------------------------------------------------------------

    #[Test]
    public function security_executes_the_callable_scheme(): void
    {
        $resolver = $this->resolver();

        $args = $this->resolve($resolver, function (#[Security(FakeBearer::class)] string $token) {
            return $token;
        });

        $this->assertSame(['token-123'], $args);
    }

    #[Test]
    public function depends_injects_the_result_of_a_callable(): void
    {
        $resolver = $this->resolver();

        $args = $this->resolve($resolver, function (
            #[Depends(dependency: '\Eorbahapi\Tests\eorbah_test_dependency')] string $value
        ) {
            return $value;
        });

        $this->assertSame(['dep-default'], $args);
    }

    #[Test]
    public function a_registered_service_is_injected(): void
    {
        $resolver = $this->resolver();
        $service = new AutowiredService();
        $resolver->set(AutowiredService::class, $service);

        $args = $resolver->resolve(function (AutowiredService $service) {
            return $service;
        });

        $this->assertSame($service, $args[0]);
    }

    #[Test]
    public function a_class_without_constructor_is_autowired(): void
    {
        $resolver = $this->resolver();

        $args = $resolver->resolve(function (AutowiredService $service) {
            return $service;
        });

        $this->assertInstanceOf(AutowiredService::class, $args[0]);
    }
}
