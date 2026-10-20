<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;

/**
 * Routage : verbes HTTP, routes statiques et paramétrées.
 */
final class RoutingExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        $app->get('/items', fn () => ['items' => ['a', 'b']]);
        $app->post('/items', fn () => ['created' => true]);
        $app->put('/items/{id}', fn (\Eorbahapi\Request $request) => ['updated' => $request->param('id')]);
        $app->delete('/items/{id}', fn (\Eorbahapi\Request $request) => ['deleted' => $request->param('id')]);

        // Route statique prioritaire sur la route paramétrée.
        $app->get('/items/new', fn () => ['form' => true]);

        // Une même route pour plusieurs verbes.
        $app->add_api_route('/multi', 'GET|POST', fn () => ['multi' => true]);

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/items', json: ['items' => ['a', 'b']]),
            self::scenario('POST', '/items', json: ['created' => true]),
            self::scenario('PUT', '/items/7', json: ['updated' => '7']),
            self::scenario('DELETE', '/items/7', json: ['deleted' => '7']),
            self::scenario('GET', '/items/new', json: ['form' => true]),
            self::scenario('GET', '/multi', json: ['multi' => true]),
            self::scenario('POST', '/multi', json: ['multi' => true]),
            self::scenario('PATCH', '/multi', status: 404),
        ];
    }
}
