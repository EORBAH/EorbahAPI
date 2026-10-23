# Path parameters

## What you will learn

Declare a `{name}` placeholder in a route, receive it as an `int` thanks to the
`#[Path]` attribute, apply validation constraints to it, and finally read a
`422` error to understand where the faulty parameter is reported.

## The code

`docs_src/PathParamsExample.php`:

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Path;

final class PathParamsExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // `{id}` est converti en int et doit être >= 1.
        $app->get('/users/{id}', function (#[Path(gte: 1)] int $id) {
            return ['id' => $id, 'type' => get_debug_type($id)];
        });

        // Placeholder nommé différemment du paramètre PHP.
        $app->get('/articles/{slug}', function (#[Path('slug', min_length: 3)] string $name) {
            return ['slug' => $name];
        });

        // Capture du reste du chemin.
        $app->get('/files/{chemin:path}', function (#[Path] string $chemin) {
            return ['path' => $chemin];
        });

        return $app;
    }
}
```

## What happens

The attribute is called `Eorbahapi\Params\Path`. It goes **on the parameter** of
the function, right before the type declaration, and it tells the framework two
things: where the value comes from (the route parameters extracted by the
router) and how to validate it.

The name of the PHP parameter and the name of the placeholder are linked by
default: `int $id` reads `{id}`. To use a different name on the URL side, pass it
as the first argument of the attribute: `#[Path('slug', min_length: 3)] string
$name` reads `{slug}` and stores it in `$name`.

**Type coercion** is automatic. The raw value of a URL is always a string;
`int $id` converts it (`"7"` becomes `7`), which is confirmed by the `type` field
of the first scenario, equal to `int`. If the conversion fails (`/users/abc`),
the response is a `422`.

The validation constraints come from `Svinx\Validator\FieldInfo`:
`Eorbahapi\Params\Path` inherits from it, so everything you already know about
Svinx works here — `ge` (>=), `le` (<=), `gt`, `lt`, `multiple_of`,
`min_length`, `max_length`, `pattern`, `choices`… The [Validations](validations.md)
chapter gives the full list.

`{chemin:path}` is a special case of the router: the `:path` suffix changes the
capture pattern, which goes from "one segment" to "the rest of the path". A
single placeholder is then enough for `/files/a/b/c.txt`.

### The scenarios of the example

```php
public static function scenarios(): array
{
    return [
        self::scenario('GET', '/users/7', json: ['id' => 7, 'type' => 'int']),
        self::scenario('GET', '/users/0', status: 422, contains: 'value_error'),
        self::scenario('GET', '/articles/php-8', json: ['slug' => 'php-8']),
        self::scenario('GET', '/articles/ab', status: 422, contains: 'value_error'),
        self::scenario('GET', '/files/a/b/c.txt', json: ['path' => 'a/b/c.txt']),
    ];
}
```

| Request | Status | Body |
| --- | --- | --- |
| `GET /users/7` | 200 | `{"id":7,"type":"int"}` |
| `GET /users/0` | 422 | `{"error":true,"status":422,"message":"Request validation error","details":[{"loc":["path","id"],"type":"value_error","msg":"Le paramètre path 'id' doit être >= 1"}]}` |
| `GET /articles/php-8` | 200 | `{"slug":"php-8"}` |
| `GET /articles/ab` | 422 | `{"error":true,"status":422,"message":"Request validation error","details":[{"loc":["path","slug"],"type":"value_error","msg":"Le paramètre path 'slug' doit avoir au moins 3 caractères"}]}` |
| `GET /files/a/b/c.txt` | 200 | `{"path":"a/b/c.txt"}` |

!!! note "How to read a 422 error"
    The body is always structured the same way:
    `details[0].loc` equals `["path", "id"]` — the location (`path`) then the
    name of the parameter as it appears in the URL (`id`) — and `details[0].msg`
    contains the Svinx sentence, in French. The handler is not executed:
    validation happens **before** the call.

!!! warning "The router normalizes the path before looking it up"
    Since the trailing slash is removed, `GET /files/` becomes `/files` and no
    longer matches `/files/{chemin:path}`: you get a `404`, not an empty path.
    And since the pattern of `{chemin:path}` is `.*`, it also captures internal
    slashes — hence the absence of an explicit `/files/` in the route.

## Summary

- `#[Path]` (namespace `Eorbahapi\Params\Path`) reads a `{name}` placeholder;
- the name of the PHP parameter serves as the default placeholder name;
- `#[Path('slug', ...)]` associates a placeholder with a PHP parameter of a different name;
- the declared type triggers coercion (`int $id` does receive `7`);
- the constraints are those of `Svinx\Validator\FieldInfo` (`ge`, `min_length`…);
- `{chemin:path}` captures the rest of the path, slashes included;
- a violated constraint yields `422` with `details[0].loc = ['path', 'id']`.

[Next: Query parameters](query-params.md){ .md-button }
