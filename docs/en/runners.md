# Custom runners

## What you will learn

Drive an EorbahAPI application from something other than a web server:
distinguish `handle()` (which **builds** the response) from `run()` (which **sends** it),
build a complete `Eorbahapi\Request` by hand (`server`, `query`, `post`,
`cookies`, `files`, `headers`, `body`), redirect a request to another URI
with `Request::withUri()`, use `APIRouter::dispatch()` for a standalone router,
and wire it all into a worker, a CLI command, a test or another application.

## The code

`docs_src/RunnersExample.php` registers two routes, then a "runner" that
processes two requests without going through the web server:

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Request;
use Eorbahapi\Response;
use Eorbahapi\Responses\JSONResponse;

final class RunnersExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        $app->get('/tasks', fn () => ['tasks' => [['id' => 1, 'title' => 'Écrire la doc']]]);
        $app->post('/tasks', fn (Request $request) => new JSONResponse(
            ['created' => $request->body('title')],
            201
        ));

        return $app;
    }

    /**
     * Un « runner » qui traite des requêtes sans passer par le serveur web.
     *
     * @return list<Response>
     */
    public static function runner(): array
    {
        $app = self::app();

        $requests = [
            new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/tasks']),
            new Request(
                server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/tasks'],
                body: ['title' => 'Relire la doc'],
                headers: ['Content-Type' => 'application/json'],
            ),
        ];

        $responses = [];
        foreach ($requests as $request) {
            $responses[] = $app->handle($request, new Response());
        }

        return $responses;
    }
}
```

The application's two entry points:

| Method | Signature | Sends the response? |
| --- | --- | --- |
| `handle()` | `handle(Request $request, ?Response $response = null): Response` | no: returns the `Response` object |
| `run()` | `run(?Request $request = null): void` | yes: `handle()` then `send()` |

`send()` does exactly three things: `http_response_code()`, `header()` for
each header, then `echo` of the body. This separation is what lets you serve
the same application over HTTP, in a CLI or in a worker.

The `new Request(...)` constructor accepts every data source, each one
injectable independently of the superglobals:

| Parameter | Content |
| --- | --- |
| `server` | override of `$_SERVER` (`REQUEST_METHOD`, `REQUEST_URI`…) |
| `query` | override of `$_GET` |
| `postData` | override of `$_POST` |
| `cookies` | override of `$_COOKIE` |
| `files` | override of `$_FILES` |
| `headers` | already extracted headers (case-insensitive names) |
| `body` | decoded body (array) or raw body (JSON string) |
| `params` | route parameters, positional |

Two tools complete the picture. `Request::withUri(string $uri): static`
returns a **copy** of the request pointing to another URI, without touching
the original: this is what `mount()` does when it strips the prefix before
handing the request to the sub-application.

```php
$request = new Request(server: ['REQUEST_URI' => '/static/assets/logo.svg']);

$sub = $request->withUri('/assets/logo.svg');

$sub->path();     // '/assets/logo.svg'
$request->path(); // '/static/assets/logo.svg'
```

`APIRouter::dispatch()` runs a route without an application around it:

```php
$router = new APIRouter();
$router->get('/users/{id}', fn (int $id) => ['id' => $id]);

$response = $router->dispatch(new Request(server: ['REQUEST_URI' => '/users/7']));
```

It returns a `Response`, or `null` when no route matches — it is up to you
to decide what to do with it (404, logging, retry). Without
`REQUEST_METHOD`, the request is treated as a `GET`.

## What happens

```php
public static function scenarios(): array
{
    return [
        self::scenario('GET', '/tasks', json: ['tasks' => [['id' => 1, 'title' => 'Écrire la doc']]]),
        self::scenario('POST', '/tasks', ['json' => ['title' => 'Relire la doc']], status: 201, json: ['created' => 'Relire la doc']),
    ];
}
```

| Request | Status | Body |
| --- | --- | --- |
| `GET /tasks` | 200 | `{"tasks":[{"id":1,"title":"Écrire la doc"}]}` |
| `POST /tasks` · `{"title":"Relire la doc"}` | 201 | `{"created":"Relire la doc"}` |

!!! warning "`run()` really sends"
    `$app->run()` writes the body to standard output and manipulates the HTTP
    headers: in a CLI, the JSON appears in the terminal and `header()` warns if
    output has already started. Reserve `run()` for the web entry point
    (`public/index.php`) and use `handle()` everywhere else. In a mounted
    sub-application, `process()` must never call `send()`: the shared response
    is filled, then sent once by the host application.

!!! tip "Four uses, a single method"
    `handle()` covers testing (`TestClient` calls it), the queue worker (one
    task = one `Request` built from the message), the CLI command (arguments
    turned into `query` or `body`) and mounting into another application
    (`withUri()` strips the prefix). The response body can be retrieved via
    `$response->body`, `$response->text()` or, if it is JSON, `$response->body`
    decoded on your side.

## Summary

- `handle(Request, ?Response)` builds and returns the response, without sending it;
- `run(?Request)` calls `handle()` then `send()`: it is the HTTP entry point;
- `new Request(...)` accepts `body`, `params`, `server`, `query`, `postData`,
  `cookies`, `files`, `headers` — everything is injectable;
- `Request::withUri($uri)` gives a copy of the request on another URI, which
  `mount()` uses to strip the prefix of sub-applications;
- `APIRouter::dispatch(Request, ?Response)` runs a single route and returns
  `null` when there is no match;
- CLI, worker, test, mounting: four runners, a single method to call.

[Next: API Reference](reference.md){ .md-button }
