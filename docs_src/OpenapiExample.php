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

        // /api/openapi.json, /api/docs, /api/docs/oauth2-redirect, /api/redoc
        // sont enregistrés automatiquement par le constructeur.

        return $app;
    }

    /**
     * Écrit le fichier utilisé par les clients et les outils (CI, codegen).
     */
    public static function write_spec(string $path): string
    {
        return self::app()->write_openapi($path);
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/articles/7?limit=5', json: ['id' => 7, 'limit' => 5]),

            self::scenario('POST', '/articles', [
                'json' => ['titre' => 'Clavier', 'prix' => 49.9, 'stock' => 12],
            ], json: ['article' => ['titre' => 'Clavier', 'prix' => 49.9, 'stock' => 12, 'resume' => null]]),

            self::scenario('GET', '/api/openapi.json', contains: '{id}'),
            self::scenario('GET', '/api/docs', contains: 'swagger-ui'),
            self::scenario('GET', '/api/redoc', contains: '<redoc'),
            self::scenario('GET', '/api/docs/oauth2-redirect', contains: 'swaggerUIRedirectOauth2'),
            self::scenario('GET', '/api/openapi.json', contains: 'OpenapiArticle'),
        ];
    }
}
