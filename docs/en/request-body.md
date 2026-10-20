# Request body

## What you will learn

Receive JSON data: validate the **entire** body with a `Svinx\Validator` model, extract **a single field** from the body, retrieve the **raw** body, and see how a failed validation becomes a `422` response.

## The code

`docs_src/BodyExample.php` first declares a model:

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Body;
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;

/**
 * Modèle de corps validé par Svinx\Validator.
 */
final class Product extends BaseModel
{
    #[Field(min_length: 2, max_length: 50)]
    public string $name;

    #[Field(gt: 0)]
    public float $price;

    #[Field(ge: 0, le: 1000)]
    public int $stock = 0;
}
```

Then the application, with the three ways to read the body:

```php
/**
 * Corps de requête : validation d'un modèle complet ou d'un champ unique.
 */
final class BodyExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Tout le corps JSON est validé par le modèle.
        $app->post('/products', function (#[Body] Product $product) {
            return ['product' => $product->model_dump()];
        });

        // Un seul champ du corps, extrait par son nom.
        $app->post('/subscribe', function (#[Body(name: 'email', min_length: 5)] string $email) {
            return ['subscribed' => $email];
        });

        // Le corps complet, brut.
        $app->post('/echo', function (#[Body] array $payload) {
            return ['received' => $payload];
        });

        return $app;
    }
}
```

`#[Body]` does only one thing: it indicates **where** to read the value
(`ParamTypes::BODY`). What the parameter receives depends on its **declared type**:

| Declared type | What is passed |
| --- | --- |
| a `Svinx\Validator\BaseModel` model | the entire body, validated by the model |
| a scalar (`string`, `int`…) | the field bearing the parameter's name |
| `array` / `mixed` | the entire body, without validation |

`#[Field]` carries the constraints, applied in the order of the model:

| Constraint | Effect |
| --- | --- |
| `min_length` / `max_length` | length of a string |
| `gt` / `ge` | strictly greater than / greater than or equal to |
| `lt` / `le` | strictly less than / less than or equal to |
| `pattern` | regular expression |
| `choices` | closed list of values |
| `alias` | exposed name, different from the property name |
| `default` | fallback value if the key is absent |

The PHP type of the property is also part of the contract: `public float $price`
coerces `49` into `49.0`, and `public int $stock = 0` provides `0` when the
`stock` key is absent from the JSON.

`model_dump()` serializes the model into an array, reusing aliases and
recursing into nested models — this is exactly what you can return from a
handler.

## What happens

| Request | Status | Body |
| --- | --- | --- |
| `POST /products` · `{"name":"Clavier","price":49.9,"stock":12}` | 200 | `{"product":{"name":"Clavier","price":49.9,"stock":12}}` |
| `POST /products` · `{"name":"C","price":0}` | 422 | `{"error":true,…,"details":[{"loc":["body","product"],"type":"value_error"}]}` |
| `POST /subscribe` · `{"email":"jean@example.com"}` | 200 | `{"subscribed":"jean@example.com"}` |
| `POST /subscribe` · `{"email":"x"}` | 422 | `{"error":true,…,"details":[{"loc":["body","email"],"type":"value_error"}]}` |
| `POST /echo` · `{"a":1,"b":[2,3]}` | 200 | `{"received":{"a":1,"b":[2,3]}}` |

The error envelope is always the same; here is the first row of the table
in detail:

```json
{
  "error": true,
  "status": 422,
  "message": "Request validation error",
  "details": [
    {
      "loc": ["body", "product"],
      "type": "value_error",
      "msg": "Le champ 'name' doit avoir au moins 2 caractères"
    }
  ]
}
```

`loc` designates the location (`body`) and the name of the faulty parameter (`product`,
`email`). For a model, it is the name of the **parameter**, not the field: the field
detail is in `msg`, produced by `Svinx\Validator`.

!!! tip "Content-Type: application/json"
    With `curl`, send `-H 'Content-Type: application/json' -d '{…}'`. The
    framework decodes the JSON body only once; if the JSON is empty or
    invalid, it falls back to `$_POST` — handy for traditional
    forms, but do not rely on it for strict JSON.

!!! warning "An invalid model is never a 400"
    A Svinx validation error becomes a `422 Unprocessable Entity`, not a
    `400`. A `400` signals an unreadable body; a `422` signals a readable
    but unacceptable body. The handler is then **never called**.

## Summary

- `#[Body]` on a `BaseModel` model: the entire body is validated by the model;
- `#[Body(name: 'email', min_length: 5)] string $email`: a single field extracted
  and validated with the attribute's constraints;
- `#[Body] array $payload`: the raw body, without validation;
- `#[Field]` carries the constraints (`min_length`, `gt`, `ge`, `le`, `pattern`,
  `choices`, `alias`, `default`);
- the declared type decides coercion, `model_dump()` handles serialization;
- any `ValidationError` from Svinx is converted into a `422` with `loc`, `type` and `msg`.

[Next: Nested models](nested-models.md){ .md-button }
