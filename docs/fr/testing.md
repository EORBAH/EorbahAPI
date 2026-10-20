# Tests

## Ce que vous allez apprendre

Tester une application EorbahAPI sans serveur HTTP : utiliser
`Eorbahapi\Testing\TestClient`, envoyer des requêtes avec `get()`, `post()`,
`put()`, `patch()`, `delete()` ou `request()`, renseigner les options (`headers`,
`query`, `json`, `form`, `body`, `cookies`, `files`, `server`), lire la réponse
avec `TestResponse` (`status()`, `headers()`, `header()`, `body()`, `text()`,
`json()`, `raw()`, `assertStatus()`, `assertSee()`, `isSuccess()`) et écrire un
test PHPUnit complet. Et comprendre **pourquoi** tout cela tient dans un seul
processus.

## Le code

`docs_src/TestingExample.php` déclare une application aux deux routes que l'on
veut couvrir :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Body;
use Eorbahapi\Params\Query;
use Eorbahapi\Testing\TestClient;

final class TestingExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        $app->get('/items', fn (#[Query(ge: 1)] int $page = 1) => ['page' => $page]);

        $app->post('/login', fn (#[Body(name: 'user', min_length: 3)] string $user) => [
            'logged' => $user,
        ]);

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/items', json: ['page' => 1]),
            self::scenario('GET', '/items?page=3', json: ['page' => 3]),
            self::scenario('GET', '/items?page=0', status: 422),
            self::scenario('POST', '/login', ['json' => ['user' => 'oumar']], json: ['logged' => 'oumar']),
            self::scenario('POST', '/login', ['json' => ['user' => 'ou']], status: 422),
        ];
    }

    /**
     * Exemple d'utilisation du client de test (voir la page « Tests »).
     */
    public static function demo(): array
    {
        $client = new TestClient(self::app());

        return [
            'status' => $client->get('/items?page=2')->status(),
            'json' => $client->get('/items?page=2')->json(),
        ];
    }
}
```

Le point qui rend tout cela possible : `TestClient` appelle
`$app->handle($request, new Response())`, et `handle()` **construit** la réponse
sans jamais l'envoyer. Aucun port n'est ouvert, aucune requête réseau n'est émise.

`new TestClient($app)` accepte ensuite les méthodes `get`, `post`, `put`,
`patch`, `delete` et la forme générique
`request(string $method, string $uri, array $options = [])`. Les options :

| Option | Effet |
| --- | --- |
| `headers` | en-têtes de la requête (noms insensibles à la casse) |
| `query` | paramètres de requête, fusionnés avec ceux de l'URI |
| `json` | corps encodé en JSON, avec `Content-Type: application/json` |
| `form` | corps de formulaire (`$_POST`), avec `Content-Type: application/x-www-form-urlencoded` |
| `body` | corps brut, sous forme de chaîne ou de tableau |
| `cookies` | cookies de la requête |
| `files` | fichiers téléversés |
| `server` | surcharges de `$_SERVER` (par exemple `REMOTE_ADDR`) |

Une réponse `TestResponse` expose de quoi lire et affirmer :

| Méthode | Résultat |
| --- | --- |
| `status()` | le statut HTTP, entier |
| `headers()` / `header($nom)` | tous les en-têtes / un en-tête par son nom |
| `body()` / `text()` | le corps sous forme de chaîne (un tableau est encodé en JSON) |
| `json()` | le corps décodé, `null` si le corps est vide |
| `raw()` | la `Eorbahapi\Response` d'origine |
| `isSuccess()` | `true` pour un statut entre 200 et 299 |
| `assertStatus($code)` | lève une `RuntimeException` si le statut diffère |
| `assertSee($fragment)` | lève une `RuntimeException` si le corps ne contient pas le fragment |

## Ce qui se passe

| Requête | Statut | Corps |
| --- | --- | --- |
| `GET /items` | 200 | `{"page":1}` |
| `GET /items?page=3` | 200 | `{"page":3}` |
| `GET /items?page=0` | 422 | `{"error":true,"status":422,"message":"Request validation error",…}` |
| `POST /login` · `{"user":"oumar"}` | 200 | `{"logged":"oumar"}` |
| `POST /login` · `{"user":"ou"}` | 422 | `{"error":true,"status":422,"message":"Request validation error",…}` |

Un test PHPUnit complet, dans l'esprit de `tests/ExamplesTest.php` :

```php
<?php

namespace Eorbahapi\Tests;

use Eorbahapi\Examples\TestingExample;
use Eorbahapi\Testing\TestClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ItemsTest extends TestCase
{
    #[Test]
    public function la_liste_est_paginee_et_validee(): void
    {
        $client = new TestClient(TestingExample::app());

        $response = $client->get('/items?page=2');

        $this->assertSame(200, $response->status());
        $this->assertSame(['page' => 2], $response->json());

        $client->post('/login', ['json' => ['user' => 'oumar']])
            ->assertStatus(200)
            ->assertSee('oumar');

        $client->get('/items?page=0')->assertStatus(422);
    }
}
```

!!! tip "Une réponse neuve par requête"
    `TestClient` crée un nouvel objet `Response` pour chaque appel : un statut ou
    un en-tête posé par une requête précédente ne fuit pas sur la suivante. Vous
    pouvez donc enchaîner les appels dans un même test sans remettre l'état à
    zéro, et un `assertStatus(200)` après un `422` teste bien le second appel.

!!! note "Assertions hors PHPUnit"
    `assertStatus()` et `assertSee()` lèvent une `RuntimeException` et ne
    dépendent pas de PHPUnit : le même code fonctionne dans un script de
    vérification, un runner CLI ou un `demo()` comme celui de l'exemple. Dans
    PHPUnit, préférez `assertSame()` sur `status()` et `json()` pour obtenir des
    messages de différence plus lisibles.

## Récapitulatif

- `new TestClient($app)` teste l'application en mémoire, sans serveur ni port ;
- `get`, `post`, `put`, `patch`, `delete`, et `request($method, $uri, $options)`
  pour tout le reste ;
- options : `headers`, `query`, `json`, `form`, `body`, `cookies`, `files`,
  `server` ;
- `TestResponse` fournit `status()`, `header()`, `headers()`, `body()`, `text()`,
  `json()`, `raw()`, `isSuccess()` ;
- `assertStatus()` et `assertSee()` affichent le corps reçu quand ils échouent ;
- `handle()` ne fait aucun envoi : c'est ce qui rend ces tests possibles.

[Suivant : Runners personnalisés](runners.md){ .md-button }
