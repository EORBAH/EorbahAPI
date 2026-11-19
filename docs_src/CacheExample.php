<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;

/**
 * Cache de l'application, activé par `new EorbahAPI(cache: true)`.
 *
 * Le cache fichier reprend le principe du cache de Twig : la valeur calculée
 * est écrite dans un fichier PHP, relu ensuite par un simple `include`. Il sert
 * au schéma OpenAPI et à l'accélération des requêtes (routes déjà appariées,
 * réflexion des paramètres).
 */
final class CacheExample extends DocsExample
{
    /**
     * Dossier de cache de l'exemple (en production : `cache/eorbahapi`).
     */
    public static function cache_dir(): string
    {
        return sys_get_temp_dir() . '/eorbahapi_example_cache';
    }

    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI(
            title: 'Catalogue',
            cache: true,
            cache_dir: self::cache_dir(),
            cache_ttl: 3600,
        );

        $app->get('/articles/{id}', fn (int $id) => ['id' => $id]);

        // La valeur n'est calculée qu'une fois, puis servie depuis le fichier.
        $app->get('/rapport', function () use ($app) {
            return $app->cache_manager()->remember('rapport', fn () => [
                'genere' => '2024-01-01',
                'lignes' => 3,
            ]);
        });

        // Le schéma OpenAPI est mis en cache fichier dès le premier appel.
        $app->get('/spec', fn () => $app->openapi());

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/articles/7', json: ['id' => 7]),
            self::scenario('GET', '/rapport', json: ['genere' => '2024-01-01', 'lignes' => 3]),
            self::scenario('GET', '/rapport', json: ['genere' => '2024-01-01', 'lignes' => 3]),
            self::scenario('GET', '/spec', contains: '"openapi"'),
        ];
    }
}
