# Query parameters

## What you will learn

Receive parameters from a URL (`?page=2`), choose between automatic resolution by name and the `#[Query]` attribute, handle default values, required parameters, nullable parameters and lists (`?tags[]=php&tags[]=api`).

## The code

`docs_src/QueryParamsExample.php` :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Query;

final class QueryParamsExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Sans attribut : les paramètres de l'URL sont résolus par leur nom.
        $app->get('/search', function (string $q = 'tout', int $page = 1) {
            return ['q' => $q, 'page' => $page];
        });

        // `skip`/`limit` avec des valeurs par défaut PHP.
        $app->get('/items', function (#[Query] int $skip = 0, #[Query] int $limit = 10) {
            return ['skip' => $skip, 'limit' => $limit];
        });

        // Paramètre obligatoire (aucun défaut).
        $app->get('/required', function (#[Query] string $token) {
            return ['token' => $token];
        });

        // Paramètre optionnel nullable.
        $app->get('/optional', function (#[Query] ?string $name = null) {
            return ['name' => $name];
        });

        // Liste : `?tags[]=php&tags[]=api`.
        $app->get('/tags', function (#[Query] array $tags = []) {
            return ['tags' => $tags];
        });

        return $app;
    }
}
```

## What happens

Three out of five routes do without an attribute: `?q=php&page=2` fills
`string $q` and `int $page` because the framework resolves each parameter **by
its name** in `Request::params()` — the route parameters, completed by the
query string. This is the shortest form, handy for filters.

The `Eorbahapi\Params\Query` attribute makes the parameter explicit. It brings
two more things: the value can carry a **default** (`default:`) and it is
validated by `Svinx\Validator` with the same constraints as `#[Path]`.

The resolution order for a missing value is always the same:

1. the default carried by the attribute (`#[Query(default: 1)]`) ;
2. the PHP default of the function (`int $limit = 10`) ;
3. `null` if the type allows it (`?string $name = null`) ;
4. otherwise a `422` error of type `missing`.

This is exactly what `/items` (PHP defaults), `/required` (no default, hence
required) and `/optional` (nullable) show.

For lists, the convention is that of HTML forms:
`?tags[]=php&tags[]=api` fills `array $tags = []`. A single `?tags=php` is
rejected — Svinx expects an array, not a string.

### The scenarios of the example

```php
public static function scenarios(): array
{
    return [
        self::scenario('GET', '/search?q=php&page=2', json: ['q' => 'php', 'page' => 2]),
        self::scenario('GET', '/search', json: ['q' => 'tout', 'page' => 1]),
        self::scenario('GET', '/items?skip=5&limit=2', json: ['skip' => 5, 'limit' => 2]),
        self::scenario('GET', '/items', json: ['skip' => 0, 'limit' => 10]),
        self::scenario('GET', '/required?token=abc', json: ['token' => 'abc']),
        self::scenario('GET', '/required', status: 422, contains: 'missing'),
        self::scenario('GET', '/optional', json: ['name' => null]),
        self::scenario('GET', '/tags?tags[]=php&tags[]=api', json: ['tags' => ['php', 'api']]),
    ];
}
```

| Request | Status | Body |
| --- | --- | --- |
| `GET /search?q=php&page=2` | 200 | `{"q":"php","page":2}` |
| `GET /search` | 200 | `{"q":"tout","page":1}` |
| `GET /items?skip=5&limit=2` | 200 | `{"skip":5,"limit":2}` |
| `GET /items` | 200 | `{"skip":0,"limit":10}` |
| `GET /required?token=abc` | 200 | `{"token":"abc"}` |
| `GET /required` | 422 | `{"error":true,"status":422,"message":"Request validation error","details":[{"loc":["query","token"],"type":"missing","msg":"Le paramètre query 'token' est requis."}]}` |
| `GET /optional` | 200 | `{"name":null}` |
| `GET /tags?tags[]=php&tags[]=api` | 200 | `{"tags":["php","api"]}` |

!!! tip "String coercion is lax, validation is not"
    A URL only carries text: `#[Query] int $limit` receives `"2"` and
    converts it to `2`, `float` accepts `"2.5"`, `bool` accepts `"true"` and `"1"`.
    On the other hand, a non-convertible value (`?limit=abc`) does not become `0`:
    it produces a `422` with a message of the type
    `int attendu, string donné ('abc')`.

!!! warning "Without an attribute, there is no validation"
    A bare parameter (`int $page = 1`) is converted by a simple PHP cast: the
    type constraint is not checked and `?page=x` silently gives
    `0`. As soon as you want an explicit rejection, bounds or a documented
    default value, declare `#[Query]` — or move on to
    [Validations](validations.md).

## Summary

- a parameter without an attribute is resolved by its name in the request ;
- `#[Query]` (namespace `Eorbahapi\Params\Query`) makes the parameter explicit and validated ;
- attribute default, then PHP default, then `null` if the type allows it ;
- no default and no `null` allowed: `422` of type `missing` ;
- a nullable optional parameter is written `#[Query] ?string $name = null` ;
- lists go through `?tags[]=a&tags[]=b` ;
- strings are converted to `int`, `float`, `bool` in Svinx's lax mode.

[Next: Validations](validations.md){ .md-button }
