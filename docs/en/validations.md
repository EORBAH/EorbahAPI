# Validations

## What you will learn

Set constraints on a parameter (`ge`, `min_length`, `pattern`, `choices`…), validate a Svinx type such as `EmailStr`, and read the `422` response with confidence: what `details` contains, and what changes with the `value_error` or `missing` error type.

## The code

`docs_src/QueryValidationsExample.php` :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Query;
use Svinx\Validator\Validators\EmailStr;

final class QueryValidationsExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Bornes numériques : ge = >=, le = <=, gt = >, lt = <.
        $app->get('/limit', function (#[Query(gte: 1, le: 100)] int $limit = 10) {
            return ['limit' => $limit];
        });

        // Longueur et expression régulière sur une chaîne.
        $app->get('/slug', function (
            #[Query('slug', min_length: 3, max_length: 20, pattern: '/^[a-z0-9-]+$/')] string $value
        ) {
            return ['slug' => $value];
        });

        // Liste fermée de valeurs autorisées.
        $app->get('/sort', function (#[Query(choices: ['asc', 'desc'])] string $order = 'asc') {
            return ['order' => $order];
        });

        // Type validé de Svinx : EmailStr.
        $app->get('/email', function (#[Query] EmailStr $email) {
            return ['email' => $email->dump()];
        });

        // Valeur par défaut portée par l'attribut (sans défaut PHP).
        $app->get('/page', function (#[Query(default: 1, gte: 1)] int $page) {
            return ['page' => $page];
        });

        return $app;
    }
}
```

## What happens

The `Path`, `Query`, `Header`, `Cookie`, `Body`, `Form` and `File` attributes all extend `Eorbahapi\Params\Param`, which extends `Svinx\Validator\FieldInfo`: so there is **only one validation grammar** in the framework.

| Constraint | Effect |
| --- | --- |
| `ge` / `gt` | value >= / > the bound |
| `le` / `lt` | value <= / < the bound |
| `multiple_of` | value multiple of the bound |
| `min_length` / `max_length` | minimum / maximum length of a string or list |
| `pattern` | regular expression (in `/…/` form), tested against the whole string |
| `choices` | closed list of allowed values |
| `default` / `default_factory` | value used when the parameter is absent (value or callable) |
| `alias` / name | name of the parameter on the wire, when it differs from the PHP parameter |
| `title` / `description` | documentation of the parameter, reused in the API reference |

A Svinx type (`EmailStr`, `UUID`…) is set directly as the parameter type.

| Type (`Svinx\Validator\Validators`) | Checks |
| --- | --- |
| `EmailStr` / `HttpUrl` | valid email address / valid HTTP(S) URL |
| `UUID` | UUID identifier |
| `Date` / `Datetime` | date, timestamp |
| `Decimal` | decimal with exact precision |
| `IPv4Address` / `IPv6Address` | IP address |
| `SecretStr` | secret masked on display |

Since `EmailStr` is not a `string`, its result is read back with `->dump()`: that is what `/email` does to return `"jean@example.com"` as JSON.

`default:` is not a duplicate of the PHP default: `#[Query(default: 1)] int $page` responds `{"page":1}` even though the PHP parameter has no default value. The attribute default is tried before the PHP one.

### The example's scenarios

```php
public static function scenarios(): array
{
    return [
        self::scenario('GET', '/limit?limit=42', json: ['limit' => 42]),
        self::scenario('GET', '/limit?limit=0', status: 422, contains: 'value_error'),
        self::scenario('GET', '/limit?limit=101', status: 422, contains: 'value_error'),
        self::scenario('GET', '/slug?slug=mon-article', json: ['slug' => 'mon-article']),
        self::scenario('GET', '/slug?slug=Mon_Article', status: 422, contains: 'value_error'),
        self::scenario('GET', '/sort?order=desc', json: ['order' => 'desc']),
        self::scenario('GET', '/sort?order=random', status: 422, contains: 'value_error'),
        self::scenario('GET', '/email?email=jean@example.com', json: ['email' => 'jean@example.com']),
        self::scenario('GET', '/email?email=invalide', status: 422, contains: 'EmailStr'),
        self::scenario('GET', '/page', json: ['page' => 1]),
        self::scenario('GET', '/page?page=3', json: ['page' => 3]),
    ];
}
```

| Request | Status | Body |
| --- | --- | --- |
| `GET /limit?limit=42` | 200 | `{"limit":42}` |
| `GET /limit?limit=0` | 422 | `value_error`: the value must be >= 1 |
| `GET /limit?limit=101` | 422 | `value_error`: the value must be <= 100 |
| `GET /slug?slug=mon-article` | 200 | `{"slug":"mon-article"}` |
| `GET /slug?slug=Mon_Article` | 422 | `value_error`: the pattern `^[a-z0-9-]+$` is not respected |
| `GET /sort?order=desc` | 200 | `{"order":"desc"}` |
| `GET /sort?order=random` | 422 | `value_error`: one of the values `asc, desc` is expected |
| `GET /email?email=jean@example.com` | 200 | `{"email":"jean@example.com"}` |
| `GET /email?email=invalide` | 422 | `value_error`: "EmailStr invalide : 'invalide' n'est pas une adresse email valide." |
| `GET /page` | 200 | `{"page":1}` |
| `GET /page?page=3` | 200 | `{"page":3}` |

### The shape of a 422 error

Every validation error produces the same envelope:

```json
{
  "error": true,
  "status": 422,
  "message": "Request validation error",
  "details": [
    {"loc": ["query", "limit"], "type": "value_error", "msg": "Le paramètre query 'limit' doit être >= 1"}
  ]
}
```

`loc` is a pair: the location (`query`, `path`, `header`…) then the name of the parameter **as it appears on the wire**. `type` distinguishes two families: `value_error` (value present but invalid) and `missing` (value absent and required). `msg` is the Svinx sentence, in French.

!!! note "The handler is never called on a validation error"
    Parameter resolution happens before invocation: if a constraint fails, the function is not executed and a single violation is enough to produce the `422` response — you have no `null` test to write.

!!! tip "Anchor your patterns"
    `pattern` is tested against the **whole** string: `/^[a-z0-9-]+$/` is correct, `/[a-z0-9-]+/` would accept `Mon_Article` by finding it somewhere.

## Summary

- `Eorbahapi\Params\Param` extends `Svinx\Validator\FieldInfo`: same constraints everywhere;
- numeric: `ge`, `gt`, `le`, `lt`, `multiple_of`;
- strings and lists: `min_length`, `max_length`, `pattern`, `choices`;
- values: `default`, `default_factory`, `alias` (name on the wire), `title`, `description`;
- Svinx types (`EmailStr`, `HttpUrl`, `UUID`, `Date`, `Datetime`, `Decimal`, `IPv4Address`, `IPv6Address`, `SecretStr`) validate the value themselves;
- an error yields `422` with `{"error":true,"status":422,"message":"Request validation error","details":[{"loc":["query","limit"],"type":"value_error","msg":"…"}]}`, where `type` is `value_error` (invalid value) or `missing` (required parameter absent).

[Next: Request body](request-body.md){ .md-button }
