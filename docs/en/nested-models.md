# Nested models

## What you will learn

Compose models: a `Svinx\Validator` model can be the property of another model. You will see recursive validation, aliases (`full_name`), dedicated types like `EmailStr`, and how an error in a sub-model surfaces as a `422`.

## The code

`docs_src/NestedBodyExample.php` defines two models, then a route:

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Body;
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;
use Svinx\Validator\Validators\EmailStr;

/**
 * Adresse, utilisée comme modèle imbriqué.
 */
final class Address extends BaseModel
{
    #[Field(min_length: 2)]
    public string $city;

    #[Field(pattern: '/^[0-9]{5}$/')]
    public string $zip;
}
```

`Address` is an ordinary model. Nothing yet marks it as nested: the declaration below is what makes the link.

```php
/**
 * Client : un modèle qui en contient un autre.
 */
final class Customer extends BaseModel
{
    #[Field(alias: 'full_name', min_length: 2, max_length: 40)]
    public string $name;

    public EmailStr $email;

    #[Field(ge: 18, le: 130)]
    public int $age = 18;

    public Address $address;
}
```

```php
/**
 * Modèles imbriqués : un modèle dans un autre modèle.
 */
final class NestedBodyExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        $app->post('/customers', function (#[Body] Customer $customer) {
            return ['customer' => $customer->model_dump()];
        });

        return $app;
    }
}
```

The link between the two models is the **property type**: because `$address` is declared as `Address`, and `Address` extends `BaseModel`, the validator recursively builds a model from the JSON object `address`. There is no configuration to write.

Three things are worth highlighting in `Customer`:

- `#[Field(alias: 'full_name')]`: the JSON uses `full_name`, the PHP property stays `$name` — more readable on both sides;
- `public EmailStr $email;`: no need for `#[Field]`, the `Svinx\Validator\Validators\EmailStr` type validates the address on its own;
- `#[Field(pattern: '/^[0-9]{5}$/')]` on `zip`: five digits, exactly.

`model_dump()` crosses model boundaries: it outputs the `full_name` alias, the age, and the complete `address` object.

## What happens

| Request | Status | Body |
| --- | --- | --- |
| `POST /customers` · `{"full_name":"Jean Dupont","email":"jean@example.com","age":30,"address":{"city":"Paris","zip":"75001"}}` | 200 | `{"customer":{"full_name":"Jean Dupont","email":"jean@example.com","age":30,"address":{"city":"Paris","zip":"75001"}}}` |
| `POST /customers` · same body with `"zip":"75"` | 422 | `{"error":true,…,"details":[{"loc":["body","customer"],"type":"value_error","msg":"Le champ 'zip' ne correspond pas au pattern attendu"}]}` |

In both cases, `loc` is `["body", "customer"]`: the error is attributed to the parameter, and `msg` names the offending field — here `zip`, a field of the **sub-model**.

!!! note "What the sub-model adds"
    A few useful variants, all `422`:

    - `zip` missing from `address`: `Le champ 'zip' est requis.` ;
    - `address` missing: `Le champ 'address' est requis.` ;
    - malformed `email`: `EmailStr invalide : … n'est pas une adresse email valide.` ;
    - `age` set to `12`: `Le champ 'age' doit être >= 18`.

    Conversely, a missing `age` yields `18` (the property's default value): the response then contains `"age":18` even if the JSON did not send it.
    The request that sends `"name"` instead of `"full_name"` also passes, because `populate_by_name` is enabled by default: the alias simply takes priority.

!!! tip "Nested model or separate model?"
    Split early: a sub-model like `Address` is reusable in `Customer`, `Supplier`, `Invoice`… Each level keeps its own constraints and its own messages, and you can test `Address` on its own with `Address::model_validate([...])` or `model_validate_json()`.

## Summary

- a property typed by a `BaseModel` triggers recursive validation;
- `#[Field(alias: 'full_name')]` decouples the wire name from the property name;
- `EmailStr` and `pattern` validate with little or no `#[Field]`;
- an error in a sub-model surfaces as a `422` with `loc = [body, parameter]` and `msg` naming the offending field;
- `model_dump()` serializes deeply, aliases included.

[Next: Forms](forms.md){ .md-button }
