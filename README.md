<div align="center">
  <img src="./docs/_images/logo.png" alt="Eor_bah545 logo" width="70%" />
  <h1>EorbahAPI</h1>
  <p>Framework PHP pour construire des API et applications web avec une syntaxe simple et moderne.</p>
  <img src="https://img.shields.io/badge/license-MIT-green" alt="License" />
</div>

<br>

> Note : la version anglaise se trouve dans [README-EN.md](README-EN.md).

**EorbahAPI** est un micro-framework PHP inspiré des éléments modernes de FastAPI, Express et des outils de validation basés sur les modèles.

## Documentation

La documentation détaillée est disponible dans le dossier [docs/fr](docs/fr/Index.md).

## Installation

Depuis le dépôt GitHub :

```bash
git clone https://github.com/EORBAH/EorbahAPI.git
cd EorbahAPI
composer install
```

## Exemple rapide

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\EorbahAPI;
use function Eorbahapi\Responses\JSONResponse;

$app = new EorbahAPI('Demo');

$app->get('/', function () {
    return JSONResponse(['message' => 'Bienvenue']);
});

$app->get('/items/{id}', function ($id) {
    return ['id' => (int) $id, 'ok' => true];
});

$app->run();
```

## Réponses

Le framework supporte des valeurs de retour directes dans les routes :

- tableaux et objets → sérialisés en JSON
- `RedirectResponse('/login', 302)` → redirection HTTP
- `JSONResponse([...])`, `HTMLResponse(...)`, `FileResponse(...)`, `StreamingResponse(...)`

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

Le projet est distribué sous licence MIT.

## Contribution

Consultez [CONTRIBUTING.md](CONTRIBUTING.md) pour participer au projet.
