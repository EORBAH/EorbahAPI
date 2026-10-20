<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;

/**
 * Premiers pas : une application minimale.
 */
final class FirstStepsExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI(title: 'Premiers pas', description: 'Mon premier service');

        $app->get('/', function () {
            return ['message' => 'Bonjour EorbahAPI'];
        });

        $app->get('/health', function () {
            return ['status' => 'ok'];
        });

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/', json: ['message' => 'Bonjour EorbahAPI']),
            self::scenario('GET', '/health', json: ['status' => 'ok']),
            self::scenario('GET', '/inconnu', status: 404, contains: 'Not Found'),
        ];
    }
}
