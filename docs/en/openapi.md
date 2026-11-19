# Documentation OpenAPI

## Ce que vous allez apprendre

Générer une spécification OpenAPI 3.1 à partir de vos routes sans écrire de
documentation à la main, servir Swagger UI et ReDoc pour tester l'API depuis le
navigateur, produire un fichier `openapi.json` exploitable par vos clients, et
mettre ce schéma en cache.

## Le code

`docs_src/OpenapiExample.php` :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Body;
use Eorbahapi\Params\Path;
use Eorbahapi\Params\Query;
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;

/**
 * Modèle documenté automatiquement dans `components.schemas`.
 */
final class OpenapiArticle extends BaseModel
{
    #[Field(min_length: 2, max_length: 80)]
    public string $titre;

    #[Field(gt: 0)]
    public float $prix;

    #[Field(ge: 0)]
    public int $stock = 0;

    public ?string $resume = null;
}

/**
 * Documentation OpenAPI automatique : Swagger UI, ReDoc et fichier JSON.
 *
 * Aucune annotation de documentation n'est nécessaire. Les attributs de
 * paramètre (`#[Path]`, `#[Query]`, `#[Body]`…) et les modèles Svinx suffisent :
 * le générateur en déduit les paramètres, leurs contraintes et les schémas.
 */
final class OpenapiExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI(
            title: 'Catalogue',
            description: 'API de démonstration de la documentation OpenAPI',
            version: '1.0.0',
            openapi_url: '/api/openapi.json',
            docs_url: '/api/docs',
            redoc_url: '/api/redoc',
            swagger_ui_oauth2_redirect_url: '/api/docs/oauth2-redirect',
        );

        $app->get('/articles/{id}', function (
            #[Path(gte: 1)] int $id,
            #[Query(ge: 1, le: 50)] int $limit = 10
        ) {
            return ['id' => $id, 'limit' => $limit];
        });

        $app->post('/articles', function (#[Body] OpenapiArticle $article) {
            return ['article' => $article->model_dump()];
        });

        return $app;
    }
}
```

Le constructeur enregistre automatiquement les routes de documentation :

| Route | Contenu |
| --- | --- |
| `GET /api/openapi.json` | la spécification OpenAPI complète |
| `GET /api/docs` | Swagger UI, pour essayer les routes |
| `GET /api/docs/oauth2-redirect` | page de retour d'un flux OAuth2 |
| `GET /api/redoc` | ReDoc, pour lire la spécification |

Les routes de documentation n'apparaissent pas dans la spécification générée.

## Options du constructeur

La documentation se configure directement sur `EorbahAPI` :

```php
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
| `title`, `description`, `version` | champs de `info` |
| `summary`, `terms_of_service` | `info.summary`, `info.termsOfService` |
| `contact`, `license_info` | `info.contact`, `info.license` |
| `openapi_url` | chemin du JSON (`null` désactive la documentation) |
| `openapi_tags` | liste `tags` de premier niveau |
| `openapi_external_docs` | bloc `externalDocs` |
| `docs_url` | chemin de Swagger UI (`null` désactive la page) |
| `redoc_url` | chemin de ReDoc (`null` désactive la page) |
| `swagger_ui_oauth2_redirect_url` | page de retour OAuth2 (`null` désactive) |
| `swagger_ui_init_oauth` | bloc `initOauth` de Swagger UI |
| `swagger_ui_parameters` | paramètres libres de Swagger UI (`docExpansion`, `persistAuthorization`…) |

`openapi_url: null` désactive la spécification, et donc Swagger UI et ReDoc.
`default_response_class` (voir la page « Réponses ») n'est pas propre à la
documentation : il fixe la classe de rendu de toutes les réponses structurées.

## Ce qui se passe

Le générateur relit les routes enregistrées (sous-applications montées
comprises) puis, pour chaque handler, les paramètres déclarés avec les attributs
de `Eorbahapi\Params` :

| Déclaration | Spécification produite |
| --- | --- |
| `#[Path(gte: 1)] int $id` | `in: path`, `required: true`, `type: integer`, `minimum: 1` |
| `#[Query(ge: 1, le: 50)] int $limit = 10` | `in: query`, `required: false`, `default: 10`, bornes |
| `#[Header('X-Api-Key')] string $key` | `in: header`, nom `X-Api-Key` |
| `#[Cookie] ?string $session` | `in: cookie`, non requis |
| `#[Body] OpenapiArticle $article` | `requestBody` et `$ref` vers `components.schemas` |
| `#[Body] array $payload` | `requestBody` avec le schéma `array` |
| `#[Form]` / `#[File]` | corps `form-urlencoded` / `multipart/form-data` |

Les types et les contraintes ne sont pas recopiés : ils viennent de
Svinx\Validator (`Type::fromReflection()->withConstraints()`), et les modèles de
`BaseModel::model_schema()`. Un `EmailStr` devient `format: email`, un
`UUID` devient `format: uuid`, une énumération devient `enum`, un modèle imbriqué
devient une référence.

Requêtes de vérification :

| Requête | Statut | Corps |
| --- | --- | --- |
| `GET /articles/7?limit=5` | 200 | `{"id": 7, "limit": 5}` |
| `POST /articles` | 200 | l'article validé et sérialisé |
| `GET /api/openapi.json` | 200 | spécification JSON (`{id}`, `OpenapiArticle`) |
| `GET /api/docs` | 200 | page Swagger UI |
| `GET /api/redoc` | 200 | page ReDoc |
| `GET /api/docs/oauth2-redirect` | 200 | page de retour OAuth2 |

!!! tip "La documentation est le contrat de validation"
    Une contrainte ajoutée à un attribut (`#[Query(le: 50)]`) apparaît
    immédiatement dans Swagger UI et dans les clients générés. Si la
    documentation et la validation divergent, c'est un bug : elles sont toutes
    deux dérivées de la même déclaration Svinx.

!!! note "Le fichier `openapi.json`"
    Pour versionner une spécification statique (CI, génération de clients),
    écrivez-la sur disque :

    ```php
    $app->write_openapi('public/openapi.json');
    ```

    La même spécification est disponible en mémoire avec `$app->openapi()` et en
    JSON avec `$app->openapi_json()`.

## Personnaliser la spécification

Les métadonnées se règlent dans le constructeur (`title`, `version`, `summary`,
`contact`…). Pour un réglage ponctuel, les options passées à `openapi()` — ou au
tableau `$options` de `openapi_routes()` — écrasent ces valeurs :

```php
$spec = $app->openapi([
    'version' => '2.4.0',
    'operations' => [
        'GET /articles/{id}' => ['summary' => 'Lire un article', 'tags' => ['articles']],
    ],
]);
```

- `title`, `description`, `version` remplacent les valeurs déduites de l'application ;
- `operations` ajoute un résumé, une description ou des étiquettes par opération ;
- `servers` déclare les serveurs de l'API ;
- `components` et `securitySchemes` complètent les composants (OAuth2, OIDC).

Les schémas `#[Security(HTTPBearer::class)]`, `HTTPBasic` et `APIKey*` sont
documentés automatiquement dans `components.securitySchemes`.

## Choisir les adresses des pages

Les options `openapi_url`, `docs_url`, `redoc_url` et
`swagger_ui_oauth2_redirect_url` du constructeur suffisent dans la plupart des
cas. Pour un placement plus fin (préfixe commun, options propres à chaque route),
la méthode `openapi_routes()` reste disponible. Les chemins y sont relatifs au
préfixe de documentation, puis le préfixe éventuel de l'application
(`new EorbahAPI(prefix: '/v1')`) s'y ajoute :

```php
$app->openapi_routes(
    '/api',
    openapi_url: '/spec.json',
    docs_url: '/swagger',
    redoc_url: '/reference',
    oauth2_redirect_url: '/swagger/oauth2',
);
// GET /api/spec.json, /api/swagger, /api/swagger/oauth2, /api/reference
```

Sans préfixe, les adresses partent de la racine de l'application :

```php
$app->openapi_routes('', docs_url: '/documentation', redoc_url: '/reference');
```

`false` désactive une route. Sans spécification, Swagger UI et ReDoc ne sont pas
enregistrés ; sans Swagger UI, le retour OAuth2 par défaut non plus.

```php
$app->openapi_routes(docs_url: false, redoc_url: false); // seul /openapi.json reste
```

Chaque route accepte enfin ses propres options, fusionnées au tableau commun :

```php
$app->openapi_routes(
    '/api',
    openapi_options: ['title' => 'Catalogue', 'version' => '2.4.0'],
    swagger_options: ['title' => 'Catalogue — essayer', 'swagger_js_url' => '…'],
    redoc_options: ['title' => 'Catalogue — lire', 'redoc_js_url' => '…'],
);
```

`openapi_options` alimente le générateur (`title`, `version`, `servers`,
`operations`…), `swagger_options` et `redoc_options` alimentent les pages
(titre, URL des fichiers JavaScript et CSS, favicon…). Le tableau `$options`
partagé sert de repli pour les trois.

## Récapitulatif

- `$app->openapi()` retourne la spécification OpenAPI 3.1 en tableau ;
- `openapi_routes()` sert `/openapi.json`, `/docs`, `/redoc` et la page OAuth2, avec des adresses et des options propres à chaque route ;
- les paramètres et les modèles sont déduits des attributs et de Svinx\Validator ;
- `write_openapi()` écrit le fichier `.json` pour les clients ;
- le schéma respecte les routes montées et masque les pages de documentation ;
- avec `new EorbahAPI(cache: true)`, la spécification est mise en cache fichier.

[Suivant : Cache](cache.md){ .md-button }
