<div align="center">
  <img src="./docs/_images/logo.png" alt="Eor_bah545 logo" width="70%" />
  <h1>EorbahAPI</h1>
  <p>PHP framework for building APIs and web applications with a clean, modern syntax.</p>
  <img src="https://img.shields.io/badge/license/MIT%2B-green" alt="License" />
</div>

<br>

**EorbahAPI** is a  PHP framework inspired by modern API tools such as FastAPI and Express.js.

## Documentation

The full documentation is available in [docs/fr/Index.md](docs/fr/Index.md).

## Installation

From GitHub:

```bash
git clone https://github.com/EORBAH/EorbahAPI.git
cd EorbahAPI
composer install
```

## Quick example

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\EorbahAPI;
use function Eorbahapi\Responses\JSONResponse;

$app = new EorbahAPI('Demo');

$app->get('/', function () {
    return JSONResponse(['message' => 'Hello']);
});

$app->get('/items/{id}', function ($id) {
    return ['id' => (int) $id, 'ok' => true];
});

$app->run();
```

## Response helpers

Routes can return plain values directly:

- arrays and objects are converted to JSON
- `RedirectResponse('/login', 302)` triggers an HTTP redirect
- `JSONResponse`, `HTMLResponse`, `FileResponse`, and `StreamingResponse` are available in the `Eorbahapi\Responses` namespace

## Validation

```php
use Eorbahapi\Validator\BaseModel;
use Eorbahapi\Validator\Field;

class UserCreate extends BaseModel
{
    public string $name;
    public int $age;

    public static function fields(): array
    {
        return [
            'name' => Field::required()->minLength(2),
            'age' => Field::required()->min(18),
        ];
    }
}
```

## License

The project is distributed under the MIT license.

## Contributing

Please consult [CONTRIBUTING.md](CONTRIBUTING.md) to contribute to the project.
