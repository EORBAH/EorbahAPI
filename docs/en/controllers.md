# Controllers

## What you will learn

Group routes into a class: the `#[Get]`, `#[Post]`, `#[Route]` attributes, mounting with `include_controller()`, and direct use of the `Eorbahapi\APIRouter` router.

## The code

`docs_src/ControllersExample.php` :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Routing\Attributes\Delete;
use Eorbahapi\Routing\Attributes\Get;
use Eorbahapi\Routing\Attributes\Middleware;
use Eorbahapi\Routing\Attributes\Post;
use Eorbahapi\Routing\Attributes\Route;

/**
 * Contrôleur utilisant les attributs de route.
 */
final class ItemsController
{
    #[Get('/items')]
    public function index()
    {
        return ['items' => ['a', 'b']];
    }

    #[Post('/items')]
    public function store()
    {
        return ['created' => true];
    }

    #[Get('/items/{id}')]
    public function show(\Eorbahapi\Request $request)
    {
        return ['id' => $request->param('id')];
    }

    #[Delete('/items/{id}')]
    public function destroy(\Eorbahapi\Request $request)
    {
        return ['deleted' => $request->param('id')];
    }

    // Une même méthode sur plusieurs verbes.
    #[Route('/ping', methods: ['GET', 'POST'])]
    #[Middleware(RequestIdMiddleware::class)]
    public function ping()
    {
        return ['pong' => true];
    }

    // Méthode publique sans attribut : pas une route.
    public function helper(): string
    {
        return 'pas une route';
    }
}

/**
 * Contrôleurs : `include_controller()` et attributs de route.
 */
final class ControllersExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Toutes les routes du contrôleur sont montées sous /api.
        $app->include_controller(new ItemsController(), prefix: '/api');

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/api/items', json: ['items' => ['a', 'b']]),
            self::scenario('POST', '/api/items', json: ['created' => true]),
            self::scenario('GET', '/api/items/42', json: ['id' => '42']),
            self::scenario('DELETE', '/api/items/42', json: ['deleted' => '42']),
            self::scenario('GET', '/api/ping', json: ['pong' => true]),
            self::scenario('POST', '/api/ping', json: ['pong' => true]),
            self::scenario('GET', '/api/helper', status: 404),
            self::scenario('GET', '/items', status: 404),
        ];
    }
}
```

## What happens

| Request | Status | Body |
| --- | --- | --- |
| `GET /api/items` | 200 | `{"items":["a","b"]}` |
| `POST /api/items` | 200 | `{"created":true}` |
| `GET /api/items/42` | 200 | `{"id":"42"}` |
| `DELETE /api/items/42` | 200 | `{"deleted":"42"}` |
| `GET /api/ping` | 200 | `{"pong":true}` |
| `POST /api/ping` | 200 | `{"pong":true}` |
| `GET /api/helper` | 404 | `Not Found` |
| `GET /items` | 404 | `Not Found` |

### One method, one attribute

The attributes in `Eorbahapi\Routing\Attributes` are `Get`, `Post`, `Put`,
`Patch`, `Delete`, `Head`, `Options` — each one fixes its verb — and `Route`, which
accepts `methods: ['GET', 'POST']` to share a handler between several
verbs. They all inherit from `Eorbahapi\Routing\Route`: classes that use
the old location `Eorbahapi\Routing\{Get, Post…}` are recognized the same
way.

The handler is a public method of the controller: its parameters are resolved
like those of a closure (see [Dependencies](dependencies.md)).

### Mounting

`$app->include_controller(new ItemsController(), prefix: '/api')` registers
each public method carrying a route attribute, prefixed with `/api`. Without a
prefix, `/items` would be served directly. A public method without an attribute
is never mounted: `helper()` returns 404, and the prefix does not exist for
`/items` without `/api`.

`include_controller()` returns `$this` and accepts, instead of an instance, the
class name, along with its constructor arguments:
`$app->include_controller(ItemsController::class, '/api', [new Logger('debug')])`.
`include_routes()` is the historical alias of `include_controller()`.

### The router, without attributes

`$app->router` is an `Eorbahapi\APIRouter`. It exposes `get`, `post`, `put`,
`patch`, `delete`, `head`, `options` and `add_api_route($chemin, $methodes,
$callback, $middlewares)`, all usable directly to build routes without a
controller — useful for generated or conditional routes:
`$app->router->add_api_route('/health', ['GET'], fn () => ['status' => 'ok'])`.
`include_router($router, prefix: '/v2')` mounts a complete `APIRouter` under a
prefix.

!!! tip "Middlewares travel with the method"
    `#[Middleware(RequestIdMiddleware::class)]` on a method applies to
    all the routes it declares; on the class, it applies to the whole
    controller, and method middlewares run after those of the
    class (see [Middlewares](middlewares.md)).

!!! note "Why controllers?"
    Closures are enough for an example or a prototype. As soon as a project
    grows, the class lets you isolate responsibilities, inject a
    service through the constructor, and mount the same controller under two
    prefixes.

## Summary

- `Eorbahapi\Routing\Attributes\{Get, Post, Put, Patch, Delete, Head, Options}` declare one route per verb;
- `#[Route('/login', methods: ['GET', 'POST'])]` shares a handler between several verbs;
- `$app->include_controller($instanceOuClasse, prefix: '/api', args: [...])` mounts the controller;
- `include_routes()` is the historical alias of `include_controller()`;
- only public methods carrying a route attribute are registered;
- `$app->router` (`Eorbahapi\APIRouter`) allows programmatic registration.

[Next: Static files](static-files.md){ .md-button }
