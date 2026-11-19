<?php

namespace Eorbahapi\Tests;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Openapi\Constants;
use Eorbahapi\Openapi\Docs;
use Eorbahapi\Openapi\OpenApiBuilder;
use Eorbahapi\Params\Body;
use Eorbahapi\Params\Cookie;
use Eorbahapi\Params\Header;
use Eorbahapi\Params\Path;
use Eorbahapi\Params\Query;
use Eorbahapi\Params\Security;
use Eorbahapi\Responses\HTMLResponse;
use Eorbahapi\Routing\Attributes\Get;
use Eorbahapi\Security\HTTP\HTTPBearer;
use Eorbahapi\Testing\TestClient;
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;
use Svinx\Validator\Validators\EmailStr;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Modèle utilisé par les tests du générateur OpenAPI.
 */
final class OpenapiTestUser extends BaseModel
{
    #[Field(min_length: 2, max_length: 50)]
    public string $name;

    #[Field(gt: 0)]
    public float $price;

    #[Field(ge: 0, le: 1000)]
    public int $stock = 0;

    public ?string $note = null;
}

/**
 * Énumération documentée comme schéma `enum`.
 */
enum OpenapiTestStatus: string
{
    case NEW = 'new';
    case PAID = 'paid';
}

final class OpenapiTestAddress extends BaseModel
{
    #[Field(min_length: 2)]
    public string $ville;
}

final class OpenapiTestOrder extends BaseModel
{
    #[Field(gt: 0)]
    public float $total;

    public OpenapiTestAddress $adresse;
}

/**
 * Contrôleur partagé : identifie les handlers par classe et méthode.
 */
final class OpenapiTestPingController
{
    #[Get('/ping')]
    public function ping(): string
    {
        return 'pong';
    }
}

class OpenapiTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/eorbah_openapi_' . uniqid('', true);
        mkdir($this->cacheDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->cacheDir . '/*') ?: [] as $file) {
            is_dir($file) ? $this->removeDir($file) : @unlink($file);
        }
        @rmdir($this->cacheDir);
    }

    private function removeDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $file) {
            is_dir($file) ? $this->removeDir($file) : @unlink($file);
        }
        @rmdir($dir);
    }

    /**
     * Application de référence : chemin, requête, en-tête, cookie, corps.
     */
    private function app(): EorbahAPI
    {
        $app = new EorbahAPI('Boutique', 'API de démonstration');

        $app->get('/users/{id}', function (
            #[Path(gte: 1)] int $id,
            #[Header('X-Api-Key', min_length: 4)] string $apiKey,
            #[Query(ge: 1, le: 100)] int $limit = 10,
            #[Cookie] ?string $session = null
        ) {
            return ['id' => $id, 'limit' => $limit, 'key' => $apiKey, 'session' => $session];
        });

        $app->post('/users', function (#[Body] OpenapiTestUser $user) {
            return $user->model_dump();
        });

        $app->get('/search', fn (#[Query] EmailStr $email) => ['email' => (string) $email]);

        return $app;
    }

    #[Test]
    public function the_specification_describes_the_application(): void
    {
        $spec = $this->app()->openapi();

        $this->assertSame(Constants::OPENAPI_VERSION, $spec['openapi']);
        $this->assertSame('Boutique', $spec['info']['title']);
        $this->assertSame('API de démonstration', $spec['info']['description']);
        $this->assertSame('0.1.0', $spec['info']['version']);
        $this->assertArrayHasKey('/users/{id}', $spec['paths']);
        $this->assertArrayHasKey('get', $spec['paths']['/users/{id}']);
        $this->assertArrayHasKey('post', $spec['paths']['/users']);
    }

    #[Test]
    public function path_and_query_parameters_come_from_svinx(): void
    {
        $operation = $this->app()->openapi()['paths']['/users/{id}']['get'];
        $parameters = [];

        foreach ($operation['parameters'] as $parameter) {
            $parameters[$parameter['name']] = $parameter;
        }

        $this->assertSame('path', $parameters['id']['in']);
        $this->assertTrue($parameters['id']['required']);
        $this->assertSame('integer', $parameters['id']['schema']['type']);
        $this->assertSame(1, $parameters['id']['schema']['minimum']);

        $this->assertSame('query', $parameters['limit']['in']);
        $this->assertFalse($parameters['limit']['required']);
        $this->assertSame(1, $parameters['limit']['schema']['minimum']);
        $this->assertSame(100, $parameters['limit']['schema']['maximum']);
        $this->assertSame(10, $parameters['limit']['schema']['default']);

        $this->assertSame('header', $parameters['X-Api-Key']['in']);
        $this->assertTrue($parameters['X-Api-Key']['required']);
        $this->assertSame(4, $parameters['X-Api-Key']['schema']['minLength']);

        $this->assertSame('cookie', $parameters['session']['in']);
        $this->assertFalse($parameters['session']['required']);
    }

    #[Test]
    public function validator_types_produce_formats(): void
    {
        $operation = $this->app()->openapi()['paths']['/search']['get'];

        $this->assertSame('email', $operation['parameters'][0]['schema']['format']);
    }

    #[Test]
    public function a_body_model_becomes_a_schema_reference(): void
    {
        $spec = $this->app()->openapi();
        $requestBody = $spec['paths']['/users']['post']['requestBody'];

        $this->assertSame(
            ['$ref' => Constants::REF_PREFIX . 'OpenapiTestUser'],
            $requestBody['content']['application/json']['schema']
        );
        $this->assertTrue($requestBody['required']);

        $schema = $spec['components']['schemas']['OpenapiTestUser'];
        $this->assertSame('object', $schema['type']);
        $this->assertSame('string', $schema['properties']['name']['type']);
        $this->assertSame(2, $schema['properties']['name']['minLength']);
        $this->assertSame(0, $schema['properties']['price']['exclusiveMinimum']);
        $this->assertSame(1000, $schema['properties']['stock']['maximum']);
        $this->assertSame(['name', 'price'], $schema['required']);
        $this->assertArrayHasKey(Constants::VALIDATION_ERROR_SCHEMA, $spec['components']['schemas']);
    }

    #[Test]
    public function the_documentation_routes_can_be_registered(): void
    {
        $app = $this->app();
        $app->get('/health', fn () => ['status' => 'ok']);
        $app->openapi_routes('/api');

        $client = new TestClient($app);

        $spec = $client->get('/api/openapi.json');
        $this->assertSame(200, $spec->status());
        $this->assertSame('application/json', $spec->header('Content-Type'));
        $this->assertArrayHasKey('/health', $spec->json()['paths']);
        $this->assertArrayNotHasKey('/api/docs', $spec->json()['paths']);
        $this->assertArrayNotHasKey('/api/openapi.json', $spec->json()['paths']);

        $this->assertStringContainsString('swagger-ui', $client->get('/api/docs')->text());
        $this->assertStringContainsString('/api/openapi.json', $client->get('/api/docs')->text());
        $this->assertStringContainsString('redoc', $client->get('/api/redoc')->text());
        $this->assertStringContainsString(
            'swaggerUIRedirectOauth2',
            $client->get('/api/docs/oauth2-redirect')->text()
        );
    }

    #[Test]
    public function each_documentation_route_accepts_its_own_options(): void
    {
        $app = $this->app();
        $app->openapi_routes(
            '/api',
            ['title' => 'Titre commun'],
            openapi_options: ['title' => 'Spécification', 'version' => '9.9.9'],
            swagger_options: [
                'title' => 'Swagger perso',
                'swagger_js_url' => 'https://cdn.example/swagger.js',
            ],
            redoc_options: [
                'title' => 'ReDoc perso',
                'redoc_js_url' => 'https://cdn.example/redoc.js',
            ],
        );

        $client = new TestClient($app);

        $spec = $client->get('/api/openapi.json')->json();
        $this->assertSame('Spécification', $spec['info']['title']);
        $this->assertSame('9.9.9', $spec['info']['version']);

        $docs = $client->get('/api/docs')->text();
        $this->assertStringContainsString('Swagger perso', $docs);
        $this->assertStringContainsString('https://cdn.example/swagger.js', $docs);

        $redoc = $client->get('/api/redoc')->text();
        $this->assertStringContainsString('ReDoc perso', $redoc);
        $this->assertStringContainsString('https://cdn.example/redoc.js', $redoc);
    }

    #[Test]
    public function shared_documentation_options_remain_the_fallback(): void
    {
        $app = $this->app();
        $app->openapi_routes('/api', ['title' => 'Titre commun']);

        $client = new TestClient($app);

        $this->assertStringContainsString('Titre commun', $client->get('/api/openapi.json')->text());
        $this->assertStringContainsString('Titre commun', $client->get('/api/docs')->text());
        $this->assertStringContainsString('Titre commun', $client->get('/api/redoc')->text());
    }

    #[Test]
    public function openapi_metadata_comes_from_the_constructor(): void
    {
        $app = new EorbahAPI(
            title: 'Catalogue',
            description: 'API de démonstration',
            version: '2.4.0',
            summary: 'Résumé court',
            terms_of_service: 'https://example.com/cgu',
            contact: ['name' => 'Équipe API', 'email' => 'api@example.com'],
            license_info: ['name' => 'MIT'],
            openapi_tags: [['name' => 'articles', 'description' => 'Gestion des articles']],
            openapi_external_docs: ['description' => 'Guide', 'url' => 'https://example.com/guide'],
        );

        $spec = $app->openapi();

        $this->assertSame('Catalogue', $spec['info']['title']);
        $this->assertSame('API de démonstration', $spec['info']['description']);
        $this->assertSame('2.4.0', $spec['info']['version']);
        $this->assertSame('Résumé court', $spec['info']['summary']);
        $this->assertSame('https://example.com/cgu', $spec['info']['termsOfService']);
        $this->assertSame('Équipe API', $spec['info']['contact']['name']);
        $this->assertSame('MIT', $spec['info']['license']['name']);
        $this->assertSame('articles', $spec['tags'][0]['name']);
        $this->assertSame('https://example.com/guide', $spec['externalDocs']['url']);
    }

    #[Test]
    public function documentation_routes_are_registered_automatically(): void
    {
        $app = new EorbahAPI('Catalogue');
        $app->get('/ping', fn () => 'pong');

        $client = new TestClient($app);

        $this->assertSame(200, $client->get('/openapi.json')->status());
        $this->assertSame(200, $client->get('/docs')->status());
        $this->assertSame(200, $client->get('/redoc')->status());
        $this->assertSame(200, $client->get('/docs/oauth2-redirect')->status());
        $this->assertSame(
            ['/openapi.json', '/docs', '/docs/oauth2-redirect', '/redoc'],
            $app->documentation_routes()
        );
    }

    #[Test]
    public function documentation_urls_can_be_set_in_the_constructor(): void
    {
        $app = new EorbahAPI(
            title: 'Catalogue',
            openapi_url: '/spec.json',
            docs_url: '/swagger',
            redoc_url: '/reference',
            swagger_ui_oauth2_redirect_url: '/swagger/oauth2',
        );
        $app->get('/ping', fn () => 'pong');

        $client = new TestClient($app);

        $this->assertStringContainsString('/spec.json', $client->get('/swagger')->text());
        $this->assertSame(200, $client->get('/reference')->status());
        $this->assertSame(200, $client->get('/swagger/oauth2')->status());
        $this->assertArrayHasKey('/ping', $client->get('/spec.json')->json()['paths']);

        $this->assertSame(404, $client->get('/openapi.json')->status());
        $this->assertSame(404, $client->get('/docs')->status());
        $this->assertSame(
            ['/spec.json', '/swagger', '/swagger/oauth2', '/reference'],
            $app->documentation_routes()
        );
    }

    #[Test]
    public function swagger_ui_options_come_from_the_constructor(): void
    {
        $app = new EorbahAPI(
            'Catalogue',
            swagger_ui_parameters: ['docExpansion' => 'none', 'persistAuthorization' => true],
            swagger_ui_init_oauth: ['clientId' => 'mon-client', 'appName' => 'Catalogue'],
        );

        $docs = $app->docs()->text();

        $this->assertStringContainsString('"docExpansion": "none"', $docs);
        $this->assertStringContainsString('"persistAuthorization": true', $docs);
        $this->assertStringContainsString('"clientId":"mon-client"', $docs);
    }

    #[Test]
    public function documentation_paths_can_be_customized(): void
    {
        // Documentation automatique désactivée : on la place manuellement.
        $app = new EorbahAPI('Boutique', 'API de démonstration', openapi_url: null);
        $app->get('/users/{id}', fn (#[Path(gte: 1)] int $id) => ['id' => $id]);

        $app->openapi_routes(
            '/api',
            openapi_url: '/spec.json',
            docs_url: '/swagger',
            redoc_url: '/reference',
            oauth2_redirect_url: '/swagger/oauth',
        );

        $client = new TestClient($app);

        $spec = $client->get('/api/spec.json');
        $this->assertSame(200, $spec->status());
        $this->assertSame('application/json', $spec->header('Content-Type'));
        $this->assertArrayHasKey('/users/{id}', $spec->json()['paths']);
        $this->assertArrayNotHasKey('/api/swagger', $spec->json()['paths']);

        $docs = $client->get('/api/swagger');
        $this->assertSame(200, $docs->status());
        $this->assertStringContainsString('/api/spec.json', $docs->text());

        $this->assertStringContainsString('swaggerUIRedirectOauth2', $client->get('/api/swagger/oauth')->text());
        $this->assertStringContainsString('redoc', $client->get('/api/reference')->text());

        // Les emplacements par défaut ne sont plus enregistrés.
        $this->assertSame(404, $client->get('/api/openapi.json')->status());
        $this->assertSame(404, $client->get('/api/docs')->status());
        $this->assertSame(404, $client->get('/api/redoc')->status());

        $this->assertSame(
            ['/api/spec.json', '/api/swagger', '/api/swagger/oauth', '/api/reference'],
            $app->documentation_routes()
        );
    }

    #[Test]
    public function documentation_paths_include_the_application_prefix(): void
    {
        $app = new EorbahAPI(prefix: '/v1');
        $app->get('/ping', fn () => 'pong');
        $app->openapi_routes();

        $client = new TestClient($app);

        $this->assertSame(200, $client->get('/v1/openapi.json')->status());
        $this->assertStringContainsString('/v1/openapi.json', $client->get('/v1/docs')->text());
        $this->assertStringContainsString('redoc', $client->get('/v1/redoc')->text());

        // Les routes de documentation restent absentes du schéma.
        $schema = $client->get('/v1/openapi.json')->json();
        $this->assertArrayNotHasKey('/v1/docs', $schema['paths']);
        $this->assertArrayNotHasKey('/v1/openapi.json', $schema['paths']);

        $this->assertSame(
            ['/v1/openapi.json', '/v1/docs', '/v1/docs/oauth2-redirect', '/v1/redoc'],
            $app->documentation_routes()
        );
    }

    #[Test]
    public function documentation_routes_can_be_disabled(): void
    {
        $app = new EorbahAPI('Boutique', docs_url: null, redoc_url: null);

        $client = new TestClient($app);

        $this->assertSame(200, $client->get('/openapi.json')->status());
        $this->assertSame(404, $client->get('/docs')->status());
        $this->assertSame(404, $client->get('/redoc')->status());
        $this->assertSame(['/openapi.json'], $app->documentation_routes());
    }

    #[Test]
    public function disabling_the_specification_disables_the_pages_that_read_it(): void
    {
        $app = new EorbahAPI('Boutique', openapi_url: null);

        $client = new TestClient($app);

        $this->assertSame(404, $client->get('/openapi.json')->status());
        $this->assertSame(404, $client->get('/docs')->status());
        $this->assertSame(404, $client->get('/redoc')->status());
        $this->assertSame([], $app->documentation_routes());
    }

    #[Test]
    public function documentation_pages_can_be_rendered_directly(): void
    {
        $app = new EorbahAPI('Boutique');

        $this->assertInstanceOf(HTMLResponse::class, $app->docs());
        $this->assertStringContainsString('SwaggerUIBundle', $app->docs()->text());
        $this->assertStringContainsString('<redoc', $app->redoc()->text());
        $this->assertStringContainsString(
            'oauth2',
            $app->swagger_ui_oauth2_redirect_html()->text()
        );

        $this->assertStringContainsString('swagger-ui', Docs::get_swagger_ui_html('/spec.json'));
        $this->assertStringContainsString('/spec.json', Docs::get_redoc_html('/spec.json'));
    }

    #[Test]
    public function the_specification_can_be_written_to_a_json_file(): void
    {
        $path = $this->cacheDir . '/openapi.json';

        $this->assertSame($path, $this->app()->write_openapi($path));
        $this->assertFileExists($path);

        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(Constants::OPENAPI_VERSION, $decoded['openapi']);
        $this->assertArrayHasKey('/users/{id}', $decoded['paths']);
    }

    #[Test]
    public function security_schemes_are_documented(): void
    {
        $app = new EorbahAPI();
        $app->get('/me', fn (#[Security(HTTPBearer::class)] string $token) => ['token' => $token]);

        $spec = $app->openapi();

        $this->assertSame(
            ['type' => 'http', 'scheme' => 'bearer'],
            $spec['components']['securitySchemes']['HTTPBearer']
        );
        $this->assertSame([['HTTPBearer' => []]], $spec['paths']['/me']['get']['security']);
    }

    #[Test]
    public function mounted_applications_are_included(): void
    {
        $app = new EorbahAPI('Principale');
        $app->get('/health', fn () => 'ok');

        $admin = new EorbahAPI();
        $admin->get('/stats', fn () => ['users' => 3]);
        $app->mount('/admin', $admin);

        $paths = $app->openapi()['paths'];

        $this->assertArrayHasKey('/health', $paths);
        $this->assertArrayHasKey('/admin/stats', $paths);
    }

    #[Test]
    public function route_placeholders_are_path_parameters(): void
    {
        $app = new EorbahAPI();
        $app->get('/files/{chemin:path}', fn (string $chemin) => $chemin);

        $operation = $app->openapi()['paths']['/files/{chemin}']['get'];

        $this->assertSame('chemin', $operation['parameters'][0]['name']);
        $this->assertSame('path', $operation['parameters'][0]['in']);
        $this->assertTrue($operation['parameters'][0]['required']);
    }

    #[Test]
    public function nested_models_and_enums_are_referenced(): void
    {
        $app = new EorbahAPI();
        $app->post('/orders', fn (#[Body] OpenapiTestOrder $order) => $order);
        $app->get('/orders', fn (#[Query] OpenapiTestStatus $status) => (string) $status);

        $spec = $app->openapi();

        $this->assertSame(
            ['$ref' => Constants::REF_PREFIX . 'OpenapiTestAddress'],
            $spec['components']['schemas']['OpenapiTestOrder']['properties']['adresse']
        );
        $this->assertArrayHasKey('OpenapiTestAddress', $spec['components']['schemas']);

        $statusSchema = $spec['paths']['/orders']['get']['parameters'][0]['schema'];
        $this->assertSame('string', $statusSchema['type']);
        $this->assertSame(['new', 'paid'], $statusSchema['enum']);
    }

    #[Test]
    public function operation_metadata_can_be_customized(): void
    {
        $app = $this->app();

        $spec = $app->openapi([
            'title' => 'Titre personnalisé',
            'version' => '2.4.0',
            'operations' => [
                'GET /users/{id}' => ['summary' => 'Lire un utilisateur', 'tags' => ['users']],
            ],
        ]);

        $this->assertSame('Titre personnalisé', $spec['info']['title']);
        $this->assertSame('2.4.0', $spec['info']['version']);
        $this->assertSame('Lire un utilisateur', $spec['paths']['/users/{id}']['get']['summary']);
        $this->assertSame(['users'], $spec['paths']['/users/{id}']['get']['tags']);
    }

    #[Test]
    public function the_specification_is_cached_when_the_cache_is_enabled(): void
    {
        $app = new EorbahAPI('Cache', cache: true, cache_dir: $this->cacheDir);
        $app->get('/ping', fn () => 'pong');

        $builder = new OpenApiBuilder($app, $app->openapi_options());
        $key = 'openapi-' . $builder->fingerprint();

        // Une valeur pré-remplie prouve que `openapi()` lit bien le magasin.
        $app->cache_manager()->store()->set(
            $key,
            ['openapi' => Constants::OPENAPI_VERSION, 'info' => ['title' => 'CACHÉ', 'version' => '1.0.0'], 'paths' => []]
        );

        $this->assertSame('CACHÉ', $app->openapi()['info']['title']);
        $this->assertNotSame([], glob($this->cacheDir . '/*.php'));

        // Une nouvelle route change l'empreinte : le cache est ignoré.
        $app->get('/other', fn () => 'ok');
        $this->assertSame('Cache', $app->openapi()['info']['title']);
        $this->assertArrayHasKey('/other', $app->openapi()['paths']);
    }

    #[Test]
    public function the_cache_key_includes_application_metadata(): void
    {
        $first = new EorbahAPI('Première', cache: true, cache_dir: $this->cacheDir);
        $first->include_controller(new OpenapiTestPingController());
        $this->assertSame('Première', $first->openapi()['info']['title']);

        // Mêmes routes, titre différent : le cache ne doit pas fuiter.
        $second = new EorbahAPI('Seconde', cache: true, cache_dir: $this->cacheDir);
        $second->include_controller(new OpenapiTestPingController());
        $this->assertSame('Seconde', $second->openapi()['info']['title']);
    }
}
