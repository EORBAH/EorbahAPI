<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Body;
use Eorbahapi\Params\Query;
use Eorbahapi\Testing\TestClient;

/**
 * Application destinée aux tests : `TestClient` envoie de vraies requêtes
 * dans le processus, sans serveur HTTP.
 *
 *     $client = new TestClient((new TestingExample())::app());
 *     $client->post('/login', ['json' => ['user' => 'oumar']])->assertStatus(200);
 */
final class TestingExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        $app->get('/items', fn (#[Query(ge: 1)] int $page = 1) => ['page' => $page]);

        $app->post('/login', fn (#[Body(name: 'user', min_length: 3)] string $user) => [
            'logged' => $user,
        ]);

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/items', json: ['page' => 1]),
            self::scenario('GET', '/items?page=3', json: ['page' => 3]),
            self::scenario('GET', '/items?page=0', status: 422),
            self::scenario('POST', '/login', ['json' => ['user' => 'oumar']], json: ['logged' => 'oumar']),
            self::scenario('POST', '/login', ['json' => ['user' => 'ou']], status: 422),
        ];
    }

    /**
     * Exemple d'utilisation du client de test (voir la page « Tests »).
     */
    public static function demo(): array
    {
        $client = new TestClient(self::app());

        return [
            'status' => $client->get('/items?page=2')->status(),
            'json' => $client->get('/items?page=2')->json(),
        ];
    }
}
