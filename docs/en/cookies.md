# Cookies

## What you will learn

Read a cookie with `#[Cookie]`, tell a required cookie from an optional cookie
(PHP default value), validate the value with Svinx constraints, understand the
`422` response — and write a cookie on the response side.

## The code

`docs_src/CookiesExample.php` :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Cookie;

/**
 * Cookies.
 */
final class CookiesExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        $app->get('/session', function (#[Cookie] string $session_id) {
            return ['session_id' => $session_id];
        });

        // Cookie optionnel, avec contrainte de format.
        $app->get('/theme', function (
            #[Cookie(choices: ['light', 'dark'])] string $theme = 'light'
        ) {
            return ['theme' => $theme];
        });

        return $app;
    }
}
```

`Cookie` lives in the `Eorbahapi\Params` namespace:
`use Eorbahapi\Params\Cookie;`. Reading happens in `$_COOKIE` — that is what
`Eorbahapi\Request::cookie()` does, and the parameter resolver queries it.
Nothing else needs wiring, and the handler signature alone describes the
contract.

| Syntax | Behavior |
| --- | --- |
| `#[Cookie] string $session_id` | **required** `session_id` cookie, `422` if missing |
| `#[Cookie] string $theme = 'light'` | **optional** cookie, `light` by default |
| `#[Cookie(choices: ['light', 'dark'])]` | restricted value, `422` otherwise |
| `#[Cookie('sid')] string $session_id` | explicit name `sid` in the cookies |

## What happens

The example scenarios describe this behavior:

```php
    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/session', [
                'cookies' => ['session_id' => 'abc123'],
            ], json: ['session_id' => 'abc123']),

            self::scenario('GET', '/session', [], status: 422, contains: 'missing'),

            self::scenario('GET', '/theme', [
                'cookies' => ['theme' => 'dark'],
            ], json: ['theme' => 'dark']),

            self::scenario('GET', '/theme', json: ['theme' => 'light']),

            self::scenario('GET', '/theme', [
                'cookies' => ['theme' => 'rose'],
            ], status: 422, contains: 'value_error'),
        ];
    }
```

| Request | Status | Body / headers |
| --- | --- | --- |
| `GET /session` with `session_id=abc123` | 200 | `{"session_id":"abc123"}` |
| `GET /session` without cookie | 422 | `missing`: the `cookie 'session_id'` parameter is required |
| `GET /theme` with `theme=dark` | 200 | `{"theme":"dark"}` |
| `GET /theme` without cookie | 200 | `{"theme":"light"}` (default value) |
| `GET /theme` with `theme=rose` | 422 | `value_error`: `rose` outside `choices` |

The body of a `422` remains a JSON
`{"error": true, "status": 422, "message": ..., "details": [...]}`: each
entry in `details` indicates `loc` (`["cookie", "theme"]`), `type` (`missing` or
`value_error`) and `msg`. The available constraints are those of
`Svinx\Validator` (`min_length`, `max_length`, `pattern`, `choices`, `ge`, `le`…),
as named arguments of the attribute.

To **write** a cookie, the response takes care of it. `Response::cookie()`
accepts a name, a value and an options array; the defaults are
safe: `path => '/'`, `secure => true`, `httponly => true`,
`samesite => 'Strict'`, `expires => 0` (session cookie). The call also records
the value in `$_COOKIE`, so the next request in the same
process reads it back. To delete a cookie, `Response::clearCookie($name)`:
it resends the cookie with a past expiration date
(`time() - 3600`) and removes it from `$_COOKIE`. In practice, in a handler:

`$response->cookie('theme', 'dark', ['path' => '/']);`

!!! tip "PHP default rather than attribute default"
    `#[Cookie] string $theme = 'light'` leaves the default in the PHP signature:
    it is the most readable form, and it works even without a cookie. The
    other two syntaxes (`default: 'light'` in the attribute, or a nullable
    type `?string $theme = null`) are equivalent.

!!! warning "A session cookie is not encrypted"
    A cookie travels in the clear on the client side: never store a
    password or an authorization role directly in it. Keep the value opaque
    (session identifier, signed token) and verify it on the server side.

## Summary

- `#[Cookie]` reads `$_COOKIE` via `Eorbahapi\Request::cookie()`;
- without a PHP default, the cookie is required and its absence yields a `422` `missing`;
- `= 'light'` (or `default:`) makes the cookie optional;
- `choices`, `pattern`, `min_length`… validate the value and produce a `422` `value_error`;
- to write, use `Response::cookie()` and `Response::clearCookie()`.

[Next: Responses](responses.md){ .md-button }
