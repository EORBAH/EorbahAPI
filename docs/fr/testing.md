# Tests

Tester une application EorbahAPI ne demande ni serveur HTTP, ni port, ni requête
réseau : tout se passe dans le même processus PHP. FastAPI s'appuie sur
`TestClient` ; EorbahAPI fournit le sien, avec la même philosophie —
l'application **construit** la réponse, le test l'inspecte.

## Pourquoi c'est simple

Le cœur du framework est `EorbahAPI::handle()` :

```php
<?php

$response = $app->handle($request, new Response());

$response->status();   // 200
$response->body();     // '{"ok":true}'
```

`handle()` ne fait aucun envoi : ni `header()`, ni `echo`. `TestClient` construit
donc une `Request` complète, la confie à `handle()` et emballe la réponse dans un
`TestResponse`. Aucun socket n'est ouvert.

## Étape 1 — Enregistrez l'application

Le client de test reçoit **l'application**, pas un serveur. Déclarez-la comme
d'habitude, dans une fonction ou un fichier que vos tests importent :

```php
<?php

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Body;
use Eorbahapi\Params\Query;

$app = new EorbahAPI(title: 'Catalogue', description: 'API de démonstration');

$app->get('/items', fn (#[Query(ge: 1, le: 100)] int $page = 1) => ['page' => $page]);

$app->post('/items', fn (#[Body(name: 'name', min_length: 2)] string $name) => [
    'created' => $name,
]);
```

## Étape 2 — Créez le client

```php
<?php

use Eorbahapi\Testing\TestClient;

$client = new TestClient($app);

$response = $client->get('/items?page=2');

$response->status();   // 200
$response->json();     // ['page' => 2]
```

`TestClient` expose aussi `post()`, `put()`, `patch()`, `delete()` et la forme
générique `request()` :

```php
<?php

$response = $client->request('GET', '/items', ['query' => ['page' => 2]]);
```

## Étape 3 — Renseignez la requête

Chaque option est injectée indépendamment des superglobales : ce que vous
envoyez est exactement ce que le handler voit.

| Option | Effet |
| --- | --- |
| `headers` | en-têtes de la requête (noms insensibles à la casse) |
| `query` | paramètres de requête, fusionnés avec ceux de l'URI |
| `json` | corps encodé en JSON, avec `Content-Type: application/json` |
| `form` | corps de formulaire (`$_POST`), avec `Content-Type: application/x-www-form-urlencoded` |
| `body` | corps brut (chaîne ou tableau) |
| `cookies` | cookies (`$_COOKIE`) |
| `files` | fichiers téléversés (`$_FILES`) |
| `server` | surcharges de `$_SERVER` (par exemple `REMOTE_ADDR`) |

```php
<?php

$response = $client->post('/items', [
    'json' => ['name' => 'Clavier'],
    'headers' => ['X-Request-Id' => 'demo-1'],
    'query' => ['dry_run' => '1'],
]);

$client->post('/items', ['form' => ['name' => 'Souris']]);

$client->post('/upload', [
    'files' => [
        'avatar' => [
            'name' => 'avatar.png',
            'type' => 'image/png',
            'tmp_name' => '/tmp/avatar.png',
            'error' => UPLOAD_ERR_OK,
            'size' => 1024,
        ],
    ],
]);
```

!!! tip "Les fichiers sont de vraies entrées `$_FILES`"
    `files` attend la structure PHP habituelle (`name`, `type`, `tmp_name`,
    `error`, `size`). Le handler peut déclarer `#[File] UploadFile $avatar` et
    recevra un `Eorbahapi\Datastructures\UploadFile` pointant sur `tmp_name`.

## Étape 4 — Lisez la réponse

`TestResponse` enveloppe la `Response` produite par l'application.

| Méthode | Résultat |
| --- | --- |
| `status()` | statut HTTP (entier) |
| `headers()` | tous les en-têtes |
| `header($nom)` | un en-tête (insensible à la casse), ou `null` |
| `body()` / `text()` | corps sous forme de chaîne (un tableau est encodé en JSON) |
| `json()` | corps décodé, `null` si le corps est vide |
| `raw()` | la `Eorbahapi\Response` d'origine |
| `isSuccess()` | `true` si le statut est entre 200 et 299 |
| `isRedirect()` | `true` pour 301, 302, 303, 307 et 308 |
| `assertStatus($code)` | lève une `RuntimeException` si le statut diffère |
| `assertSee($fragment)` | lève une `RuntimeException` si le corps ne contient pas le fragment |

## Étape 5 — Écrivez le test PHPUnit

Placez vos tests dans `tests/` et lancez PHPUnit. Le fichier ci-dessous est
complet et autonome :

```php
<?php

namespace Tests;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Query;
use Eorbahapi\Testing\TestClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ItemsTest extends TestCase
{
    private function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        $app->get('/items', fn (#[Query(ge: 1, le: 100)] int $page = 1) => ['page' => $page]);

        return $app;
    }

    #[Test]
    public function la_page_est_validee(): void
    {
        $client = new TestClient($this->app());

        $response = $client->get('/items?page=2');

        $this->assertSame(200, $response->status());
        $this->assertSame(['page' => 2], $response->json());

        $client->get('/items?page=0')->assertStatus(422);
    }
}
```

```bash
./vendor/bin/phpunit
./vendor/bin/phpunit --filter ItemsTest
./vendor/bin/phpunit tests/ItemsTest.php
```

## Tester les erreurs de validation

Une valeur invalide produit une réponse 422 dont le corps contient les détails
de Svinx\Validator, localisés par paramètre :

```php
<?php

$response = $client->post('/items', ['json' => ['name' => 'X']]);

$response->assertStatus(422);

$detail = $response->json()['details'][0];
// ['loc' => ['body', 'name'], 'type' => 'value_error', 'msg' => '...']
```

Le format est stable et suit FastAPI : `loc` indique l'emplacement et le nom du
paramètre, `type` la nature de l'erreur, `msg` le message lisible.

## Tester la documentation

Le constructeur enregistre automatiquement les routes de documentation ; vous
pouvez les tester comme n'importe quelle route :

```php
<?php

$client->get('/openapi.json')->assertStatus(200);
$client->get('/docs')->assertSee('swagger-ui');
$client->get('/redoc')->assertSee('<redoc');
```

!!! note "Une réponse neuve par requête"
    `TestClient` crée un `Response` neuf à chaque appel : un statut ou un
    en-tête posé par une requête précédente ne fuit pas sur la suivante. Vous
    pouvez donc enchaîner les appels sans réinitialiser l'application.

!!! warning "N'appelez pas `run()` dans un test"
    `run()` envoie réellement la réponse (`header()`, `echo`). Utilisez
    `handle()` — c'est ce que fait `TestClient` — pour rester dans le processus
    et garder des tests rapides et silencieux.

## Récapitulatif

- `TestClient` teste l'application en mémoire, sans serveur ni socket ;
- `get`, `post`, `put`, `patch`, `delete` et `request($method, $uri, $options)` ;
- options : `headers`, `query`, `json`, `form`, `body`, `cookies`, `files`, `server` ;
- `TestResponse` expose `status()`, `headers()`, `header()`, `body()`, `text()`,
  `json()`, `raw()`, `isSuccess()`, `isRedirect()` ;
- `assertStatus()` et `assertSee()` affichent le corps reçu en cas d'échec ;
- les erreurs de validation sont des 422 avec `details` localisés ;
- `handle()` ne fait aucun envoi : c'est ce qui rend les tests possibles.

[Suivant : Runners personnalisés](runners.md){ .md-button }
