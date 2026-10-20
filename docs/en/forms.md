# Forms

## What you will learn

Read an `application/x-www-form-urlencoded` or `multipart/form-data` form with `#[Form]`: values arrive as **strings** and are coerced before validation, exactly like query parameters.

## The code

`docs_src/FormExample.php`:

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Form;

/**
 * Données de formulaire (`application/x-www-form-urlencoded`).
 */
final class FormExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Chaque champ est coercé puis validé.
        $app->post('/login', function (
            #[Form(min_length: 3)] string $username,
            #[Form(min_length: 6)] string $password,
        ) {
            return ['username' => $username, 'password_length' => strlen($password)];
        });

        // Un modèle peut recevoir un formulaire complet.
        $app->post('/profile', function (
            #[Form(ge: 18, le: 130)] int $age,
            #[Form(choices: ['fr', 'en'])] string $lang = 'fr',
        ) {
            return ['age' => $age, 'lang' => $lang];
        });

        return $app;
    }
}
```

`#[Form]` comes from `Eorbahapi\Params\Form` and reads `$_POST` (via `Request::post()`). The field name on the wire is the name of the PHP parameter; `#[Form(name: 'e-mail')]` is enough to change it.

Coercion is the central point here. A form only carries text: `age=30` arrives as `'30'`. The resolver builds the declared type (`int`) in **lax** mode and lets Svinx convert, then apply the attribute's constraints:

| Sent | Received in the handler |
| --- | --- |
| `age=30` | `30` (`int`) |
| `age=abc` | `422` — coercion impossible |
| `lang=en` | `'en'` |
| `lang` absent | `'fr'`, the PHP default value |

The available constraints are those of any parameter: `min_length`, `max_length`, `pattern`, `choices`, `ge` / `le`, `gt` / `lt`.

## What happens

| Request | Status | Body |
| --- | --- | --- |
| `POST /login` · `username=oumar&password=secret123` | 200 | `{"username":"oumar","password_length":9}` |
| `POST /login` · `username=ou&password=secret123` | 422 | `{"error":true,…,"details":[{"loc":["form","username"],"type":"value_error"}]}` |
| `POST /profile` · `age=30&lang=en` | 200 | `{"age":30,"lang":"en"}` |
| `POST /profile` · `age=12` | 422 | `{"error":true,…,"details":[{"loc":["form","age"],"type":"value_error"}]}` |

The `loc` starts with `form`, not `body`: that is what distinguishes form data from JSON data in the error report. The corresponding message, for `age=12`, is `Le paramètre form 'age' doit être >= 18`.

!!! warning "`#[Body]` does not read forms, and vice versa"
    `#[Body]` reads the JSON body (decoded `php://input`), `#[Form]` reads `$_POST`. Sending JSON to `/profile` therefore does not give `30` but a `422` error of type `missing`: `Le paramètre form 'age' est requis.` Choose the attribute that matches the `Content-Type` sent by the client.

!!! note "`#[File]` extends `#[Form]`"
    The hierarchy is `Param` → `Body` → `Form` → `File`. A `#[File]` parameter therefore accepts the same constraints as a `#[Form]` parameter, and a `multipart/form-data` form can mix text fields (`#[Form]`) and files (`#[File]`) in the same handler — see the next page.

## Summary

- `#[Form]` (`Eorbahapi\Params\Form`) reads `$_POST`;
- values arrive as strings and are coerced to the declared type: `'30'` becomes `30`;
- the attribute's constraints apply after coercion (`min_length`, `ge` / `le`, `choices`…);
- `loc` is `form` in `422` errors;
- `#[Body]` (JSON) and `#[Form]` (form) are two distinct sources;
- `#[File]` extends `#[Form]`.

[Next: Files](files.md){ .md-button }
