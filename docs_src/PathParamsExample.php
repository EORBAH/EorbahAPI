<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Path;

/**
 * Paramètres de chemin, avec contraintes Svinx\Validator.
 */
final class PathParamsExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // `{id}` est converti en int et doit être >= 1.
        $app->get('/users/{id}', function (#[Path(gte: 1)] int $id) {
            return ['id' => $id, 'type' => get_debug_type($id)];
        });

        // Placeholder nommé différemment du paramètre PHP.
        $app->get('/articles/{slug}', function (#[Path('slug', min_length: 3)] string $name) {
            return ['slug' => $name];
        });

        // Capture du reste du chemin.
        $app->get('/files/{chemin:path}', function (#[Path] string $chemin) {
            return ['path' => $chemin];
        });

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/users/7', json: ['id' => 7, 'type' => 'int']),
            self::scenario('GET', '/users/0', status: 422, contains: 'value_error'),
            self::scenario('GET', '/articles/php-8', json: ['slug' => 'php-8']),
            self::scenario('GET', '/articles/ab', status: 422, contains: 'value_error'),
            self::scenario('GET', '/files/a/b/c.txt', json: ['path' => 'a/b/c.txt']),
        ];
    }
}
