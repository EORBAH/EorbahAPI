# Headers

## What you will learn

Read an HTTP header from a handler parameter with `#[Header]`: case-insensitive
lookup, the FastAPI convention that turns `user_agent` into `User-Agent`, the
explicit name, Svinx validation constraints, and the `422` response returned
when a required header is missing.

## The code

`docs_src/HeadersExample.php`:

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Header;

/**
 * En-têtes HTTP.
 */
final class HeadersExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // `token` est cherché sous le nom `token` puis `Token` (insensible à la casse).
        $app->get('/token', function (#[Header] string $token) {
            return ['token' => $token];
        });

        // Nom explicite, contraintes Svinx appliquées à la valeur.
        $app->get('/api-key', function (#[Header('X-Api-Key', min_length: 8)] string $key) {
            return ['key' => $key];
        });

        // Convention FastAPI : `user_agent` lit l'en-tête `User-Agent`.
        $app->get('/agent', function (#[Header] string $user_agent) {
            return ['agent' => $user_agent];
        });

        return $app;
    }
}
```

A header is declared like any other parameter: the **PHP type** decides the
conversion, the **attribute** decides the source. `Header` lives in the
`Eorbahapi\Params` namespace and is imported with
`use Eorbahapi\Params\Header;`.

Three forms are enough to cover everything:

| Form | Header read |
| --- | --- |
| `#[Header] string $token` | `Token` (or `token`, case is ignored) |
| `#[Header] string $user_agent` | `User-Agent` (`_` converted to `-`) |
| `#[Header('X-Api-Key')] string $key` | `X-Api-Key` exactly |

## What happens

The scenarios of the example describe this behavior:

```php
    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/token', [
                'headers' => ['Token' => 'abc'],
            ], json: ['token' => 'abc']),

            self::scenario('GET', '/api-key', [
                'headers' => ['X-Api-Key' => 'secret-123'],
            ], json: ['key' => 'secret-123']),

            self::scenario('GET', '/api-key', [
                'headers' => ['X-Api-Key' => 'court'],
            ], status: 422, contains: 'value_error'),

            self::scenario('GET', '/agent', [
                'headers' => ['User-Agent' => 'EorbahBot/1.0'],
            ], json: ['agent' => 'EorbahBot/1.0']),

            self::scenario('GET', '/token', [], status: 422, contains: 'missing'),
        ];
    }
```

| Request | Status | Body / headers |
| --- | --- | --- |
| `GET /token` with `Token: abc` | 200 | `{"token":"abc"}` |
| `GET /api-key` with `X-Api-Key: secret-123` | 200 | `{"key":"secret-123"}` |
| `GET /api-key` with `X-Api-Key: court` | 422 | `value_error`: 5 characters for `min_length: 8` |
| `GET /agent` with `User-Agent: EorbahBot/1.0` | 200 | `{"agent":"EorbahBot/1.0"}` |
| `GET /token` without header | 422 | `missing`: the `header 'token'` parameter is required |

A validation error never returns an HTML page: the body is a JSON
`{"error": true, "status": 422, "message": ..., "details": [...]}`, where each
entry in `details` carries `loc` (`["header", "token"]`), `type` (`missing` or
`value_error`) and `msg`. The constraints tested here are those of
`Svinx\Validator`: `min_length`, `max_length`, `pattern`, `ge`, `choices`…
they are written as named arguments of the attribute.

!!! tip "Case and dashes are handled"
    HTTP header names are case-insensitive. The framework therefore compares
    names in lowercase and also tries the `_` → `-` variant: a client that sends
    `token`, `Token` or `TOKEN` feeds the same parameter, and
    `#[Header] string $user_agent` does read `User-Agent`.

!!! warning "An explicit name freezes the name on the wire"
    As soon as you pass a name to the attribute (`#[Header('X-Api-Key')]`), the
    PHP parameter name is no longer used: `$key` reads `X-Api-Key`, not `key`.
    This is the only way to be sure of the name sent by a third-party client.

## Summary

- `#[Header]` goes on a handler parameter, like `#[Query]` or `#[Path]`;
- lookup is case-insensitive, and `_` becomes `-` (FastAPI convention);
- `#[Header('X-Api-Key')]` enforces the exact header name;
- Svinx constraints (`min_length`, `pattern`, `choices`…) validate the value;
- a missing header and an invalid value both produce a `422` with `missing` / `value_error`.

[Next: Cookies](cookies.md){ .md-button }
