# First steps

## What you will learn

Create an application, register routes, return JSON, choose the HTTP status — and understand the two entry points: `run()` and `handle()`.

## A minimal application

`docs_src/FirstStepsExample.php`:

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;

final class FirstStepsExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI(title: 'Premiers pas', description: 'Mon premier service');

        $app->get('/', function () {
            return ['message' => 'Bonjour EorbahAPI'];
        });

        $app->get('/health', function () {
            return ['status' => 'ok'];
        });

        return $app;
    }
}
```

Two routes, two handlers. The constructor accepts a title, a description and a `debug` mode: this information is used for the documentation and for rendering errors.

## What a handler returns

A handler can return:

| Returned type | Result |
| --- | --- |
| `array` / `object` | JSON body (`Content-Type` not forced) |
| `Response` | the response as is |
| `string` | text body |
| `'redirect:code:url'` | redirect (status + `Location` header) |
| `null` / `true` | the current response, unchanged |

```php
$app->get('/auto', fn () => ['ok' => true]);                       // {"ok":true}
$app->get('/text', fn () => 'bonjour');                            // bonjour
$app->get('/created', fn () => new JSONResponse(['ok' => true], 201));
$app->get('/go', fn () => 'redirect:301:/auto');
```

## `run()` and `handle()`

```php
$app->run();                                   // lit la requête réelle, envoie la réponse
$response = $app->handle(new Request(server: [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/',
]));                                           // construit la réponse, ne l'envoie pas
$app->run($request);                           // lit une requête fournie, envoie la réponse
```

`handle()` is the heart of the framework: it is what lets you test the application without an HTTP server, or plug it into another transport ([Custom runners](runners.md)).

## The example's scenarios

```php
public static function scenarios(): array
{
    return [
        self::scenario('GET', '/', json: ['message' => 'Bonjour EorbahAPI']),
        self::scenario('GET', '/health', json: ['status' => 'ok']),
        self::scenario('GET', '/inconnu', status: 404, contains: 'Not Found'),
    ];
}
```

| Request | Status | Body |
| --- | --- | --- |
| `GET /` | 200 | `{"message":"Bonjour EorbahAPI"}` |
| `GET /health` | 200 | `{"status":"ok"}` |
| `GET /inconnu` | 404 | `Not Found` |

## Run the example yourself

```php
<?php

require 'vendor/autoload.php';

use Eorbahapi\Examples\FirstStepsExample;
use Eorbahapi\Testing\TestClient;

$client = new TestClient(FirstStepsExample::app());

$response = $client->get('/');
echo $response->status();       // 200
echo $response->text();         // {"message":"Bonjour EorbahAPI"}
```

!!! tip "Two ways to serve"
    - `php -S localhost:8000 -t public` for the browser and `curl`;
    - `TestClient` for tests: no port, no socket, no latency.

## Summary

- an application is an `EorbahAPI` object;
- `$app->get($path, $handler)` registers a route (same for `post`, `put`, `patch`, `delete`, `head`, `options`);
- a handler returns data, a `Response` or a string;
- `run()` serves HTTP, `handle()` serves everything else.

[Next: Routing](routing.md){ .md-button }
