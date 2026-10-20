<div align="center">
  <h1>EorbahAPI</h1>
  <p>Framework web PHP pour construire des API : routes déclaratives, paramètres typés validés par <code>Svinx\Validator</code>, pipeline HTTP testable.</p>
  <img src="https://img.shields.io/badge/license/MIT%2B-green" alt="License" />
</div>

<br>

**EorbahAPI** réunit trois idées : des routes déclaratives (`#[Get]`, `#[Query]`,
`#[Body]`…), une validation inspirée de Pydantic (via
[Svinx\Validator](https://github.com/svinx-org/validator)) et un point d'entrée
`handle(Request): Response` qui construit la réponse sans l'envoyer — donc
testable, et réutilisable par n'importe quel runner (CLI, worker, autre app).

## Démarrage rapide

```bash
composer require eor_bah545/eorbahapi
```

```php
<?php
// public/index.php

require __DIR__ . '/../vendor/autoload.php';

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\{Path, Query};

$app = new EorbahAPI(debug: true);

$app->get('/items/{id}', function (
    #[Path(ge: 1)] int $id,
    #[Query(ge: 1, le: 100)] int $limit = 10,
) {
    return ['id' => $id, 'limit' => $limit];
});

$app->run();
```

```console
$ php -S localhost:8000 -t public
$ curl "http://localhost:8000/items/7?limit=3"
{"id":7,"limit":3}
```

## Ce que le framework fournit

| Domaine | Élément |
| --- | --- |
| Routage | `get/post/put/patch/delete/head/options`, `add_api_route`, routes `{id}` et `{chemin:path}` |
| Paramètres | `#[Path]`, `#[Query]`, `#[Header]`, `#[Cookie]`, `#[Body]`, `#[Form]`, `#[File]`, `#[Security]`, `#[Depends]` |
| Validation | contraintes Svinx (`ge`, `le`, `min_length`, `pattern`, `choices`…), modèles `BaseModel`, erreurs 422 détaillées |
| Réponses | `JSONResponse`, `HTMLResponse`, `RedirectResponse`, `StreamingResponse`, `EventStreamResponse`, chaîne `redirect:code:url` |
| Middlewares | globaux, de route, `#[Middleware(Classe::class, args: [...])]`, CORS, session, hôtes de confiance, rate limiting |
| Sécurité | `HTTPBearer`, `HTTPBasic`, clés d'API, OAuth2/OIDC, JWT |
| Applications montées | `StaticFiles`, `JsonRPC` 2.0, sous-applications `EorbahAPI` |
| Tests | `TestClient` / `TestResponse` — aucun serveur, aucun socket |
| Contrôleurs | `#[Route]`, `#[Get]`… + `$app->include_controller(new X(), prefix: '/api')` |

## Documentation

Le cours complet (25 chapitres) est dans [`docs/fr`](docs/fr/index.md) :

- démarrer : [Installation](docs/fr/installation.md) · [Premiers pas](docs/fr/first-steps.md) ;
- le cœur : [Routage](docs/fr/routing.md) · [Paramètres](docs/fr/query-params.md) · [Validations](docs/fr/validations.md) · [Corps de requête](docs/fr/request-body.md) ;
- aller plus loin : [Sécurité](docs/fr/security.md) · [Middlewares](docs/fr/middlewares.md) · [Tests](docs/fr/testing.md) · [Runners](docs/fr/runners.md) ;
- référence : [Référence API](docs/fr/reference.md).

Chaque chapitre s'appuie sur un fichier de [`docs_src/`](docs_src) **exécuté par
la suite de tests** : la documentation ne peut pas dériver du code.

Le site est construit avec **MkDocs** (thème Material) :

```bash
python scripts/build_docs.py --install   # une fois : crée .venv-docs/ et installe MkDocs
./scripts/docs_start.sh                  # http://localhost:4000 (rechargement à chaud)
python scripts/build_docs.py             # construit build/ (publié sur GitHub Pages)
```

## Validation

```php
use Eorbahapi\Params\Body;
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;

class Product extends BaseModel
{
    #[Field(min_length: 2, max_length: 50)]
    public string $name;

    #[Field(gt: 0)]
    public float $price;
}

$app->post('/products', fn (#[Body] Product $product) => $product->model_dump());
```

Une valeur invalide produit une réponse `422` :

```json
{
  "error": true,
  "status": 422,
  "message": "Request validation error",
  "details": [{"loc": ["query", "limit"], "type": "value_error", "msg": "Le paramètre query 'limit' doit être >= 1"}]
}
```

## Tests

```bash
composer test                          # toute la suite (177 tests)
./vendor/bin/phpunit --filter ExamplesTest   # valide les exemples de la doc
```

```php
use Eorbahapi\EorbahAPI;
use Eorbahapi\Testing\TestClient;

$app = new EorbahAPI();
$app->get('/ping', fn () => ['pong' => true]);

$response = (new TestClient($app))->get('/ping');

$response->assertStatus(200)->json();   // ['pong' => true]
```

## Développer avec un agent

La carte du projet destinée aux assistants de code se trouve dans
[`.agents/skills/eorbahapi`](.agents/skills/eorbahapi/SKILL.md) : architecture,
pipeline d'une requête, invariants, recettes, conventions de tests et de doc.

## Licence

Distribué sous licence MIT. Voir [LICENSE](LICENSE) et
[CONTRIBUTING.md](CONTRIBUTING.md).
