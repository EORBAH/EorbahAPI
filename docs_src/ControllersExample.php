<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Routing\Attributes\Delete;
use Eorbahapi\Routing\Attributes\Get;
use Eorbahapi\Routing\Attributes\Middleware;
use Eorbahapi\Routing\Attributes\Post;
use Eorbahapi\Routing\Attributes\Route;

/**
 * Contrôleur utilisant les attributs de route.
 */
final class ItemsController
{
    #[Get('/items')]
    public function index()
    {
        return ['items' => ['a', 'b']];
    }

    #[Post('/items')]
    public function store()
    {
        return ['created' => true];
    }

    #[Get('/items/{id}')]
    public function show(\Eorbahapi\Request $request)
    {
        return ['id' => $request->param('id')];
    }

    #[Delete('/items/{id}')]
    public function destroy(\Eorbahapi\Request $request)
    {
        return ['deleted' => $request->param('id')];
    }

    // Une même méthode sur plusieurs verbes.
    #[Route('/ping', methods: ['GET', 'POST'])]
    #[Middleware(RequestIdMiddleware::class)]
    public function ping()
    {
        return ['pong' => true];
    }

    // Méthode publique sans attribut : pas une route.
    public function helper(): string
    {
        return 'pas une route';
    }
}

/**
 * Contrôleurs : `include_controller()` et attributs de route.
 */
final class ControllersExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Toutes les routes du contrôleur sont montées sous /api.
        $app->include_controller(new ItemsController(), prefix: '/api');

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/api/items', json: ['items' => ['a', 'b']]),
            self::scenario('POST', '/api/items', json: ['created' => true]),
            self::scenario('GET', '/api/items/42', json: ['id' => '42']),
            self::scenario('DELETE', '/api/items/42', json: ['deleted' => '42']),
            self::scenario('GET', '/api/ping', json: ['pong' => true]),
            self::scenario('POST', '/api/ping', json: ['pong' => true]),
            self::scenario('GET', '/api/helper', status: 404),
            self::scenario('GET', '/items', status: 404),
        ];
    }
}
