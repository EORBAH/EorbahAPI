# Routing

## What you will learn

Register a route for each HTTP verb, declare a parameterized route, attach several verbs to the same path with `add_api_route()` — and know what the router does with the trailing slash, route priority and an unknown URL.

## The code

`docs_src/RoutingExample.php`:

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;

final class RoutingExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        $app->get('/items', fn () => ['items' => ['a', 'b']]);
        $app->post('/items', fn () => ['created' => true]);
        $app->put('/items/{id}', fn (\Eorbahapi\Request $request) => ['updated' => $request->param('id')]);
        $app->delete('/items/{id}', fn (\Eorbahapi\Request $request) => ['deleted' => $request->param('id')]);

        // Route statique prioritaire sur la route paramétrée.
        $app->get('/items/new', fn () => ['form' => true]);

        // Une même route pour plusieurs verbes.
        $app->add_api_route('/multi', 'GET|POST', fn () => ['multi' => true]);

        return $app;
    }
}
```

## What happens

A route is a **path + verb** pair. The seven verbs each have their method: `get`, `post`, `put`, `patch`, `delete`, `head`, `options`, all of the form `$app->verb($path, $handler, $middlewares = [])`.

`add_api_route($path, $methods, $handler)` accepts either a string separated by vertical bars (`'GET|POST'`) or an array (`['GET', 'POST']`). The verb is uppercased at registration: a `PATCH` on `/multi` therefore finds nothing, since `/multi` only exists for `GET` and `POST`.

The router sorts routes into two families:

- **static routes** — a path without `{…}`, stored in `$routes[verb][path]`;
- **parameterized routes** — a path with `{…}`, stored in `$dynamicRoutes[verb]` and compiled into a regular expression (`{id}` becomes `[^/]+`, `{path:path}` becomes `.*`).

The lookup checks **static routes first**, then parameterized routes: a route like `GET /items/new` will therefore never be captured by an `{id}` equal to `new`, even if you add `GET /items/{id}` later.

The parameterized route receives its values through `Request::param('id')`, which always returns a **string** (`"7"`, not `7`) as long as no typing is declared. The following chapters show how to get a real `int`.

### The example's scenarios

```php
public static function scenarios(): array
{
    return [
        self::scenario('GET', '/items', json: ['items' => ['a', 'b']]),
        self::scenario('POST', '/items', json: ['created' => true]),
        self::scenario('PUT', '/items/7', json: ['updated' => '7']),
        self::scenario('DELETE', '/items/7', json: ['deleted' => '7']),
        self::scenario('GET', '/items/new', json: ['form' => true]),
        self::scenario('GET', '/multi', json: ['multi' => true]),
        self::scenario('POST', '/multi', json: ['multi' => true]),
        self::scenario('PATCH', '/multi', status: 404),
    ];
}
```

| Request | Status | Body |
| --- | --- | --- |
| `GET /items` | 200 | `{"items":["a","b"]}` |
| `POST /items` | 200 | `{"created":true}` |
| `PUT /items/7` | 200 | `{"updated":"7"}` |
| `DELETE /items/7` | 200 | `{"deleted":"7"}` |
| `GET /items/new` | 200 | `{"form":true}` |
| `GET /multi` | 200 | `{"multi":true}` |
| `POST /multi` | 200 | `{"multi":true}` |
| `PATCH /multi` | 404 | `Not Found` |

!!! note "The trailing slash is ignored, the root becomes the empty route"
    The router normalizes each path with `rtrim($path, '/')`: `/items/` and even `/items//` reach the `/items` route. Direct consequence: `/` is registered as the **empty** route, and `GET /` responds to it normally. On the other hand, case matters: `GET /ITEMS` matches nothing.

!!! warning "`HEAD` and `OPTIONS` are not implicit"
    Declaring `GET /items` does not create the `HEAD /items` route: a `HEAD` returns `404`. If you want to respond to `head` or `options`, register them explicitly with `$app->head(...)` or `$app->options(...)`.

An unknown route returns status `404` and the text body `Not Found` — not JSON. You can change this global behavior with `default_route()`: [Error handling](errors.md).

## Summary

- `$app->get()`, `post()`, `put()`, `patch()`, `delete()`, `head()`, `options()`;
- `add_api_route($path, 'GET|POST', $handler)` for several verbs at once;
- a static route is always examined before a parameterized route;
- `{id}` captures one segment, `{path:path}` captures the rest of the path;
- the trailing slash is stripped, so `/` is the empty route and `/items/` equals `/items`;
- an unregistered verb or an unknown path gives `404` (`Not Found`, customizable with `default_route()`).

[Next: Path parameters](path-params.md){ .md-button }
