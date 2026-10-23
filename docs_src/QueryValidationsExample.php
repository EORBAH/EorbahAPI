<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Query;
use Svinx\Validator\Validators\EmailStr;

/**
 * Validation des paramètres de requête avec les contraintes Svinx\Validator.
 */
final class QueryValidationsExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Bornes numériques : gte = >=, lte = <=, gt = >, lt = <.
        $app->get('/limit', function (#[Query(gte: 1, lte: 100)] int $limit = 10) {
            return ['limit' => $limit];
        });

        // Longueur et expression régulière sur une chaîne.
        $app->get('/slug', function (
            #[Query('slug', min_length: 3, max_length: 20, pattern: '/^[a-z0-9-]+$/')] string $value
        ) {
            return ['slug' => $value];
        });

        // Liste fermée de valeurs autorisées.
        $app->get('/sort', function (#[Query(choices: ['asc', 'desc'])] string $order = 'asc') {
            return ['order' => $order];
        });

        // Type validé de Svinx : EmailStr.
        $app->get('/email', function (#[Query] EmailStr $email) {
            return ['email' => $email->dump()];
        });

        // Valeur par défaut portée par l'attribut (sans défaut PHP).
        $app->get('/page', function (#[Query(default: 1, gte: 1)] int $page) {
            return ['page' => $page];
        });

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/limit?limit=42', json: ['limit' => 42]),
            self::scenario('GET', '/limit?limit=0', status: 422, contains: 'value_error'),
            self::scenario('GET', '/limit?limit=101', status: 422, contains: 'value_error'),
            self::scenario('GET', '/slug?slug=mon-article', json: ['slug' => 'mon-article']),
            self::scenario('GET', '/slug?slug=Mon_Article', status: 422, contains: 'value_error'),
            self::scenario('GET', '/sort?order=desc', json: ['order' => 'desc']),
            self::scenario('GET', '/sort?order=random', status: 422, contains: 'value_error'),
            self::scenario('GET', '/email?email=jean@example.com', json: ['email' => 'jean@example.com']),
            self::scenario('GET', '/email?email=invalide', status: 422, contains: 'EmailStr'),
            self::scenario('GET', '/page', json: ['page' => 1]),
            self::scenario('GET', '/page?page=3', json: ['page' => 3]),
        ];
    }
}
