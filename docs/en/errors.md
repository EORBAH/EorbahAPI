# Error handling

## What you will learn

Raise a controlled HTTP error, produce a 422 validation response, register a handler for your own exceptions, understand the 500 response — and customize the 404.

## The code

`docs_src/ErrorsExample.php`:

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Exceptions\HTTPException;
use Eorbahapi\Exceptions\ValidationException;
use Eorbahapi\Responses\JSONResponse;

/**
 * Gestion des erreurs : exceptions HTTP, validation, gestionnaire personnalisé.
 */
final class ErrorsExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // HTTPException : statut et message contrôlés.
        $app->get('/forbidden', function () {
            throw new HTTPException(403, 'Accès interdit');
        });

        // ValidationException : réponse 422 avec le détail des erreurs.
        $app->post('/validate', function () {
            throw new ValidationException(
                [['loc' => ['body', 'name'], 'type' => 'missing', 'msg' => 'Le champ name est requis.']],
                'Request validation error'
            );
        });

        // Gestionnaire personnalisé pour un type d'exception.
        $app->setExceptionHandler(\DomainException::class, fn (\Throwable $error) => new JSONResponse(
            ['domain' => $error->getMessage()],
            400
        ));
        $app->get('/domain', function () {
            throw new \DomainException('règle métier violée');
        });

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/forbidden', status: 403, json: [
                'error' => true,
                'status' => 403,
                'message' => 'Accès interdit',
            ]),

            self::scenario('POST', '/validate', ['json' => []], status: 422, json: [
                'error' => true,
                'status' => 422,
                'message' => 'Request validation error',
                'details' => [['loc' => ['body', 'name'], 'type' => 'missing', 'msg' => 'Le champ name est requis.']],
            ]),

            self::scenario('GET', '/domain', status: 400, json: ['domain' => 'règle métier violée']),
        ];
    }
}
```

## What happens

| Request | Status | Body |
| --- | --- | --- |
| `GET /forbidden` | 403 | `{"error":true,"status":403,"message":"Accès interdit"}` |
| `POST /validate` | 422 | `{"error":true,"status":422,"message":"Request validation error","details":[{"loc":["body","name"],"type":"missing","msg":"Le champ name est requis."}]}` |
| `GET /domain` | 400 | `{"domain":"règle métier violée"}` |

### The framework's exceptions

`Eorbahapi\Exceptions\HTTPException($code, $message, $headers)` carries a status, a message and optional headers, exposed by `getStatusCode()` and `getHeaders()`. `Eorbahapi\Exceptions\ValidationException($errors, $message, $headers)` carries a list of errors in the FastAPI format (`loc`, `type`, `msg`) and always produces a 422; `RequestValidationError` is the variant raised automatically when a query parameter fails validation.

You can raise these exceptions from any handler: the framework converts them into a `Response` before anything is sent.

### The default handlers

`Eorbahapi\ExceptionHandlers` registers three handlers when the application starts. `handleExceptionAsResponse()` first looks for a handler for the exact class of the exception, then for the first handler whose type accepts the exception (`instanceof`):

| Exception | Response |
| --- | --- |
| `HTTPException` | JSON `{error, status, message}` with the exception's status and headers |
| `ValidationException` | JSON `{error, status, message, details}` with a 422 |
| `\Throwable` | JSON `{error, status, message, debug}` with a 500 |

### Your own handler

`$app->setExceptionHandler(Class::class, callable)` associates an exception with a function that receives `($exception, $request, $response)` and returns a `Response` — or anything a handler can return. This is how `\DomainException` becomes a 400 with a business body here. Register the most specific handler: for the same family of exceptions, the first one registered wins.

!!! warning "The 500 response stays generic"
    An unhandled exception produces `{"error":true,"status":500,"message":"Internal
    Server Error","debug":null}`. The `debug` field only contains the real message
    with `new EorbahAPI(debug: true)` (or `APP_DEBUG=1`, `APP_ENV=dev`). In
    production, leave `debug` at `false`: internal details must not leak.

!!! tip "Customizing the 404"
    `$app->default_route(404, $handler)` replaces the `Not Found` response: the
    handler receives its parameters through the resolver (a `Request` is injectable)
    and the response takes the configured status. Called without a handler,
    `$app->default_route(404)` returns an empty response with that status.

## Summary

- `new HTTPException(403, 'Accès interdit')` produces a JSON `{error, status, message}`;
- `ValidationException($errors, $message)` produces a 422 enriched with `details`;
- the three default handlers of `Eorbahapi\ExceptionHandlers` cover HTTP, validation and `\Throwable`;
- `$app->setExceptionHandler(Class::class, fn ($e) => new JSONResponse(...))` adds your own;
- an unhandled exception yields a generic 500, detailed only in `debug` mode;
- `$app->default_route(404, $handler)` customizes the unknown route.

[Next: JSON-RPC](rpc.md){ .md-button }
