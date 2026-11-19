# Documentation OpenAPI

## Ce que vous allez apprendre

Obtenir une documentation OpenAPI 3.1 sans l'écrire à la main, la servir avec
Swagger UI et ReDoc, la personnaliser, et générer un fichier `openapi.json` pour
vos clients.

## Une documentation automatique

Chaque route est introspectée : paramètres (`#[Path]`, `#[Query]`, `#[Header]`,
`#[Cookie]`, `#[Body]`, `#[Form]`, `#[File]`), typage et contraintes Svinx,
modèles `BaseModel`, schémas de sécurité. Aucune annotation de documentation
n'est nécessaire.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Query;
use Eorbahapi\Params\Path;

$app = new EorbahAPI(
    title: 'Catalogue',
    description: 'API de démonstration',
    version: '1.0.0',
);

$app->get('/articles/{id}', function (
    #[Path(gte: 1)] int $id,
    #[Query(ge: 1, le: 50)] int $limit = 10,
) {
    return ['id' => $id, 'limit' => $limit];
});

$app->run();
```

Les routes de documentation sont enregistrées automatiquement à la création de
l'application :

| Route | Contenu |
| --- | --- |
| `GET /openapi.json` | spécification OpenAPI 3.1 |
| `GET /docs` | Swagger UI pour essayer les routes |
| `GET /docs/oauth2-redirect` | page de retour d'un flux OAuth2 |
| `GET /redoc` | ReDoc pour la lecture |

Ces routes sont exclues du schéma généré.

## Options du constructeur

```php
<?php

$app = new EorbahAPI(
    title: 'Catalogue',
    description: 'API de démonstration',
    version: '1.0.0',
    summary: 'Résumé de l\'API',
    terms_of_service: 'https://example.com/cgu',
    contact: ['name' => 'Équipe API', 'email' => 'api@example.com'],
    license_info: ['name' => 'MIT'],
    openapi_url: '/api/openapi.json',
    openapi_tags: [['name' => 'articles', 'description' => 'Gestion des articles']],
    openapi_external_docs: ['description' => 'Guide', 'url' => 'https://example.com/guide'],
    docs_url: '/api/docs',
    redoc_url: '/api/redoc',
    swagger_ui_oauth2_redirect_url: '/api/docs/oauth2-redirect',
    swagger_ui_init_oauth: ['clientId' => 'mon-client'],
    swagger_ui_parameters: ['docExpansion' => 'none', 'persistAuthorization' => true],
);
```

| Option | Rôle |
| --- | --- |
| `title`, `description`, `version` | champs `info` |
| `summary`, `terms_of_service` | `info.summary`, `info.termsOfService` |
| `contact`, `license_info` | `info.contact`, `info.license` |
| `openapi_url` | chemin du JSON (`null` désactive la doc) |
| `openapi_tags` | `tags` de premier niveau |
| `openapi_external_docs` | bloc `externalDocs` |
| `docs_url` / `redoc_url` | chemins des pages (`null` désactive) |
| `swagger_ui_oauth2_redirect_url` | retour OAuth2 (`null` désactive) |
| `swagger_ui_init_oauth` | bloc `initOauth` de Swagger UI |
| `swagger_ui_parameters` | paramètres libres (`docExpansion`, `persistAuthorization`…) |

## Ce qui est documenté

| Déclaration | Spécification produite |
| --- | --- |
| `#[Path(gte: 1)] int $id` | `in: path`, `required: true`, `type: integer`, `minimum: 1` |
| `#[Query(ge: 1, le: 50)] int $limit = 10` | `in: query`, `default: 10`, bornes |
| `#[Header('X-Api-Key')] string $key` | `in: header` |
| `#[Cookie] ?string $session` | `in: cookie`, optionnel |
| `#[Body] Modele $m` | `requestBody` et `$ref` vers `components.schemas` |
| `#[Form]` / `#[File]` | `form-urlencoded` / `multipart/form-data` |
| `#[Security(HTTPBearer::class)]` | `security` + `components.securitySchemes` |

Les formats Svinx deviennent des `format` OpenAPI : `EmailStr` → `email`,
`UUID` → `uuid`, énumération → `enum`, modèle imbriqué → `$ref`. Les réponses
422 sont générées avec le schéma `HTTPValidationError`.

## Récupérer la spécification

```php
<?php

$spec = $app->openapi();                 // tableau PHP
$json = $app->openapi_json();            // JSON (pretty print)
$app->write_openapi('public/openapi.json'); // fichier pour les clients
```

Les options passées à `openapi()` surchargent celles du constructeur :

```php
<?php

$spec = $app->openapi([
    'version' => '2.4.0',
    'operations' => [
        'GET /articles/{id}' => ['summary' => 'Lire un article', 'tags' => ['articles']],
    ],
]);
```

## Placement avancé

Pour des adresses ou des options par page, `openapi_routes()` reste disponible :

```php
<?php

$app->openapi_routes(
    '/api',
    openapi_url: '/spec.json',
    docs_url: '/swagger',
    redoc_url: '/reference',
    oauth2_redirect_url: '/swagger/oauth2',
    openapi_options: ['title' => 'Catalogue', 'version' => '2.4.0'],
    swagger_options: ['swagger_js_url' => 'https://cdn.example/swagger.js'],
    redoc_options: ['redoc_js_url' => 'https://cdn.example/redoc.js'],
);
// GET /api/spec.json, /api/swagger, /api/swagger/oauth2, /api/reference
```

Les chemins sont relatifs au préfixe de documentation ; `false` désactive une
route, et si `openapi_url` est désactivée, Swagger UI et ReDoc ne sont pas
enregistrés.

## Cache

Avec `new EorbahAPI(cache: true)`, la spécification est mise en cache fichier et
n'est recalculée que si les routes, les options ou les fichiers des handlers
changent (empreinte SHA-256). Voir [Cache](cache.md).

## Récapitulatif

- la documentation est générée depuis les routes et Svinx ;
- `/openapi.json`, `/docs`, `/redoc` et le retour OAuth2 sont automatiques ;
- les métadonnées et les adresses se règlent dans le constructeur ;
- `openapi()`, `openapi_json()` et `write_openapi()` récupèrent la spec ;
- `openapi_routes()` permet un placement avancé et des options par page ;
- le schéma est mis en cache avec `cache: true`.

[Suivant : Cache](cache.md){ .md-button }
