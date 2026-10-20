# Middlewares

## What you will learn

Write a middleware with `MiddlewareInterface`, register it globally or on a
route, interrupt the chain by returning a `Response`, declare a middleware with
the `#[Middleware]` attribute on a method or on a class, and know in which order
middlewares execute.

## The code

`docs_src/MiddlewaresExample.php` :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Middlewares\MiddlewareInterface;
use Eorbahapi\Request;
use Eorbahapi\Response;
use Eorbahapi\Routing\Attributes\Middleware;
use Eorbahapi\Routing\Attributes\Post;
use Eorbahapi\Routing\Attributes\Route;

/**
 * Middleware qui ajoute un identifiant de requête à la réponse.
 */
final class RequestIdMiddleware implements MiddlewareInterface
{
    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        $response->headers['X-Request-Id'] = $request->getHeader('X-Request-Id') ?? 'genere';

        return $next();
    }
}

/**
 * Middleware qui refuse les requêtes sans en-tête d'autorisation.
 */
final class GuardMiddleware implements MiddlewareInterface
{
    public function __construct(private string $header = 'X-Token') {}

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        if ($request->getHeader($this->header) === null) {
            return new \Eorbahapi\Responses\JSONResponse(['error' => 'jeton manquant'], 403);
        }

        return $next();
    }
}

/**
 * Contrôleur illustrant les attributs de route et de middleware.
 */
final class MiddlewaresController
{
    #[Route('/login', methods: ['GET'])]
    #[Middleware(RequestIdMiddleware::class)]
    public function login()
    {
        return ['status' => 'ok'];
    }

    #[Post('/private')]
    #[Middleware(GuardMiddleware::class, args: ['header' => 'X-Token'])]
    public function privateArea()
    {
        return ['private' => true];
    }
}
```

And the application that wires them up:

```php
final class MiddlewaresExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Middleware global : s'applique à toutes les routes.
        $app->add_middleware(RequestIdMiddleware::class);

        $app->get('/public', fn () => ['public' => true]);

        // Middleware de route via le paramètre de route.
        $app->get('/guarded', fn () => ['guarded' => true], [GuardMiddleware::class]);

        // Contrôleur avec #[Middleware].
        $app->include_controller(new MiddlewaresController());

        return $app;
    }
}
```

A middleware receives the request, the **shared** response and a `$next`
successor. It can read from and write to the response before the call, then
after. If it never calls `$next()`, the chain stops: this is the *short-circuit*
used by `GuardMiddleware` to answer `403` without running the handler.

## What happens

| Request | Status | Body / headers |
| --- | --- | --- |
| `GET /public` | 200 | `{"public":true}` + `X-Request-Id: genere` |
| `GET /guarded` without `X-Token` | 403 | `{"error":"jeton manquant"}` |
| `GET /guarded` with `X-Token: abc` | 200 | `{"guarded":true}` |
| `GET /login` | 200 | `{"status":"ok"}`, middleware set by attribute |
| `POST /private` with `X-Token: abc` | 200 | `{"private":true}` |
| `POST /private` without `X-Token` | 403 | `{"error":"jeton manquant"}` |

The possible registrations, from broadest to most precise:

| Scope | Registration |
| --- | --- |
| Global | `$app->add_middleware(RequestIdMiddleware::class)` |
| Global with arguments | `$app->add_middleware(GuardMiddleware::class, ['header' => 'X-Token'])` |
| Route | 3rd argument of `$app->get(...)`: `[GuardMiddleware::class]` |
| Method | `#[Middleware(GuardMiddleware::class, args: ['header' => 'X-Token'])]` |
| Class | `#[Middleware(...)]` on the controller, for all its routes |

A middleware's arguments are those of its constructor: an associative array
(`['header' => 'X-Token']`) uses PHP named arguments, an indexed array passes
values in order. The `#[Middleware]` attribute is repeatable and lives in
`Eorbahapi\Routing\Attributes\Middleware`; on a class, its middlewares run
**before** those of the methods, and route middlewares are inserted between the
two.

The execution order is the registration order, but each middleware **wraps** the
next one: for two middlewares `A` then `B`, you get
`before-a → before-b → handler → after-b → after-a`. Headers set before
`$next()` are therefore visible to the following middlewares, and those set
after `$next()` override the previous ones.

The legacy style is still accepted: a class that exposes
`process(Request $request, Response $response, callable $next)` is called like an
`__invoke()`. The framework tries `process()`, then `handle()`, then direct
invocation of the instance.

!!! tip "Global middlewares: instance or class"
    `add_middleware()` accepts a class (instantiated once and reused for all
    requests) or an already-built instance, handy for injecting a dependency such
    as a cache client or a rate limiter.

!!! warning "Do not return `null` after a short-circuit"
    If you interrupt the chain, return the `Response` (or an array, which will be
    encoded). A `null` means "nothing to change" and leaves the shared response
    as is, with the default `200` status.

## Summary

- a middleware is a `MiddlewareInterface` with `__invoke(Request, Response, callable $next)`;
- `$app->add_middleware()` applies it to all routes, the 3rd argument of `$app->get()` to one route;
- `#[Middleware(Class::class, args: [...])]` declares the same intent on a method or a class;
- returning a `Response` without calling `$next()` cuts the chain;
- the order is wrapped: `before-a, before-b, after-b, after-a`;
- the `process(Request, Response, callable $next)` style is still supported.

[Next: Controllers](controllers.md){ .md-button }
