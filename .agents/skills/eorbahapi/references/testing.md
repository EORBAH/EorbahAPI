# Écrire des tests avec `TestClient`

## Principe

`EorbahAPI::handle(Request, ?Response): Response` construit la réponse **sans
l'envoyer**. `TestClient` exploite ce point d'entrée : aucun serveur, aucun
socket, aucun `exit`.

```php
use Eorbahapi\EorbahAPI;
use Eorbahapi\Testing\TestClient;
use PHPUnit\Framework\TestCase;

class MonTest extends TestCase
{
    public function test_ping(): void
    {
        $app = new EorbahAPI();
        $app->get('/ping', fn () => ['pong' => true]);

        $client = new TestClient($app);
        $response = $client->get('/ping');

        $response->assertStatus(200);
        $this->assertSame(['pong' => true], $response->json());
    }
}
```

## Options de `request()`

| Option | Effet |
| --- | --- |
| `headers` | en-têtes de requête (noms insensibles à la casse) |
| `query` | paramètres ajoutés à ceux de l'URI |
| `json` | corps encodé en JSON + `Content-Type: application/json` |
| `form` | `$_POST` + `Content-Type: application/x-www-form-urlencoded` |
| `body` | corps brut (chaîne) |
| `cookies` | `$_COOKIE` |
| `files` | `$_FILES` (tableau `tmp_name`, `name`, `type`, `error`, `size`) |
| `server` | surcharges de `$_SERVER` |

```php
$client->post('/users', ['json' => ['name' => 'Jean']]);
$client->get('/items?page=2');
$client->get('/secret', ['headers' => ['Authorization' => 'Bearer x']]);
$client->post('/upload', ['files' => ['avatar' => ['name' => 'a.txt', 'tmp_name' => __FILE__, 'type' => 'text/plain', 'error' => UPLOAD_ERR_OK, 'size' => 10]]]);
$client->get('/session', ['cookies' => ['session_id' => 'abc']]);
```

## `TestResponse`

```php
$response->status(): int
$response->headers(): array
$response->header('Content-Type'): mixed   // insensible à la casse, null si absent
$response->body(): string                  // tableau => JSON encodé
$response->text(): string
$response->json(): mixed                   // null si le corps est vide
$response->raw(): Response                 // objet d'origine
$response->isSuccess(): bool               // 2xx
$response->isRedirect(): bool
$response->assertStatus(int $code): self   // lève RuntimeException si différent
$response->assertSee(string $needle): self
```

## Tester la validation

```php
$response = $client->get('/items?limit=0');

$response->assertStatus(422);
$errors = $response->json()['details'];

$this->assertSame(['query', 'limit'], $errors[0]['loc']);
$this->assertSame('value_error', $errors[0]['type']);
```

Types d'erreur : `value_error` (valeur invalide), `missing` (paramètre requis
absent), `file` (téléversement en échec).

## Tester les modèles Svinx

```php
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;

class CreateUser extends BaseModel
{
    #[Field(min_length: 2)] public string $name;
}

$app->post('/users', fn (#[Body] CreateUser $user) => $user->model_dump());

$ok = $client->post('/users', ['json' => ['name' => 'Jean']]);
$ko = $client->post('/users', ['json' => ['name' => 'J']]);

$this->assertSame(200, $ok->status());
$this->assertSame(422, $ko->status());
```

## Tester un middleware

```php
class TraceMiddleware implements MiddlewareInterface
{
    public static array $calls = [];

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        self::$calls[] = $request->path();

        return $next();
    }
}

$app->add_middleware(TraceMiddleware::class);
$app->get('/x', fn () => 'ok');

(new TestClient($app))->get('/x');

$this->assertSame(['/x'], TraceMiddleware::$calls);
```

Les états statiques doivent être réinitialisés dans `setUp()`.

## Tester les exemples de la documentation

`tests/ExamplesTest.php` charge `docs_src/registry.php`, appelle `app()` puis
exécute chaque scénario. Pour ajouter un exemple testé :

1. créer `docs_src/MonExample.php` (`extends DocsExample`, `app()`, `scenarios()`) ;
2. l'ajouter à `docs_src/registry.php` ;
3. `./vendor/bin/phpunit --filter ExamplesTest`.

```php
public static function scenarios(): array
{
    return [
        self::scenario('GET', '/items', json: ['items' => []]),
        self::scenario('GET', '/items/0', status: 422, contains: 'value_error'),
        self::scenario('GET', '/redirect', status: 301, headers: ['Location' => '/items']),
    ];
}
```

## Isoler les superglobales

`TestClient` injecte tout : `$_GET`, `$_POST`, `$_COOKIE`, `$_FILES`, `$_SERVER`
et les en-têtes ne sont **pas** modifiés par les tests. Si un test touche
directement aux superglobales, restaurez-les dans `tearDown()`.

## Fichiers temporaires

Les tests de `StaticFiles`, `TwigTemplates` et upload créent leurs fichiers dans
`sys_get_temp_dir()` et les suppriment dans `tearDown()`.
