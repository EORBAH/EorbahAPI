<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Query;

/**
 * Paramètres de requête (`?clé=valeur`) typés automatiquement.
 */
final class QueryParamsExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Sans attribut : les paramètres de l'URL sont résolus par leur nom.
        $app->get('/search', function (string $q = 'tout', int $page = 1) {
            return ['q' => $q, 'page' => $page];
        });

        // `skip`/`limit` avec des valeurs par défaut PHP.
        $app->get('/items', function (#[Query] int $skip = 0, #[Query] int $limit = 10) {
            return ['skip' => $skip, 'limit' => $limit];
        });

        // Paramètre obligatoire (aucun défaut).
        $app->get('/required', function (#[Query] string $token) {
            return ['token' => $token];
        });

        // Paramètre optionnel nullable.
        $app->get('/optional', function (#[Query] ?string $name = null) {
            return ['name' => $name];
        });

        // Liste : `?tags[]=php&tags[]=api`.
        $app->get('/tags', function (#[Query] array $tags = []) {
            return ['tags' => $tags];
        });

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/search?q=php&page=2', json: ['q' => 'php', 'page' => 2]),
            self::scenario('GET', '/search', json: ['q' => 'tout', 'page' => 1]),
            self::scenario('GET', '/items?skip=5&limit=2', json: ['skip' => 5, 'limit' => 2]),
            self::scenario('GET', '/items', json: ['skip' => 0, 'limit' => 10]),
            self::scenario('GET', '/required?token=abc', json: ['token' => 'abc']),
            self::scenario('GET', '/required', status: 422, contains: 'missing'),
            self::scenario('GET', '/optional', json: ['name' => null]),
            self::scenario('GET', '/tags?tags[]=php&tags[]=api', json: ['tags' => ['php', 'api']]),
        ];
    }
}
