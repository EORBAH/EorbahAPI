# Tests

## What you will learn

Test an EorbahAPI application without an HTTP server: use
`Eorbahapi\Testing\TestClient`, send requests with `get()`, `post()`,
`put()`, `patch()`, `delete()` or `request()`, fill in the options (`headers`,
`query`, `json`, `form`, `body`, `cookies`, `files`, `server`), read the response
with `TestResponse` (`status()`, `headers()`, `header()`, `body()`, `text()`,
`json()`, `raw()`, `assertStatus()`, `assertSee()`, `isSuccess()`) and write a
complete PHPUnit test. And understand **why** all of this fits in a single
process.

## The code

`docs_src/TestingExample.php` declares an application with the two routes we
want to cover:

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

        $app->get('/items', fn (#[Query(gte: 1)] int $page = 1) => ['page' => $page]);

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

The point that makes all of this possible: `TestClient` calls
`$app->handle($request, new Response())`, and `handle()` **builds** the response
without ever sending it. No port is opened, no network request is emitted.

`new TestClient($app)` then accepts the methods `get`, `post`, `put`,
`patch`, `delete` and the generic form
`request(string $method, string $uri, array $options = [])`. The options:

| Option | Effect |
| --- | --- |
| `headers` | request headers (case-insensitive names) |
| `query` | query parameters, merged with those of the URI |
| `json` | body encoded as JSON, with `Content-Type: application/json` |
| `form` | form body (`$_POST`), with `Content-Type: application/x-www-form-urlencoded` |
| `body` | raw body, as a string or an array |
| `cookies` | request cookies |
| `files` | uploaded files |
| `server` | overrides of `$_SERVER` (for example `REMOTE_ADDR`) |

A `TestResponse` response exposes what you need to read and assert:

| Method | Result |
| --- | --- |
| `status()` | the HTTP status, as an integer |
| `headers()` / `header($nom)` | all headers / one header by its name |
| `body()` / `text()` | the body as a string (an array is encoded as JSON) |
| `json()` | the decoded body, `null` if the body is empty |
| `raw()` | the original `Eorbahapi\Response` |
| `isSuccess()` | `true` for a status between 200 and 299 |
| `assertStatus($code)` | throws a `RuntimeException` if the status differs |
| `assertSee($fragment)` | throws a `RuntimeException` if the body does not contain the fragment |

## What happens

| Request | Status | Body |
| --- | --- | --- |
| `GET /items` | 200 | `{"page":1}` |
| `GET /items?page=3` | 200 | `{"page":3}` |
| `GET /items?page=0` | 422 | `{"error":true,"status":422,"message":"Request validation error",…}` |
| `POST /login` · `{"user":"oumar"}` | 200 | `{"logged":"oumar"}` |
| `POST /login` · `{"user":"ou"}` | 422 | `{"error":true,"status":422,"message":"Request validation error",…}` |

A complete PHPUnit test, in the spirit of `tests/ExamplesTest.php`:

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

!!! tip "A fresh response per request"
    `TestClient` creates a new `Response` object for each call: a status or
    a header set by a previous request does not leak into the next one. You
    can therefore chain calls in the same test without resetting the state
    to zero, and an `assertStatus(200)` after a `422` really tests the second call.

!!! note "Assertions outside PHPUnit"
    `assertStatus()` and `assertSee()` throw a `RuntimeException` and do not
    depend on PHPUnit: the same code works in a verification script, a CLI
    runner or a `demo()` like the one in the example. In PHPUnit, prefer
    `assertSame()` on `status()` and `json()` to get more readable diff
    messages.

## Summary

- `new TestClient($app)` tests the application in memory, without a server or port;
- `get`, `post`, `put`, `patch`, `delete`, and `request($method, $uri, $options)`
  for everything else;
- options: `headers`, `query`, `json`, `form`, `body`, `cookies`, `files`,
  `server`;
- `TestResponse` provides `status()`, `header()`, `headers()`, `body()`, `text()`,
  `json()`, `raw()`, `isSuccess()`;
- `assertStatus()` and `assertSee()` display the received body when they fail;
- `handle()` does not send anything: that is what makes these tests possible.

[Next: Custom runners](runners.md){ .md-button }
