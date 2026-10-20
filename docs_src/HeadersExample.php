<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Header;

/**
 * En-têtes HTTP.
 */
final class HeadersExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // `token` est cherché sous le nom `token` puis `Token` (insensible à la casse).
        $app->get('/token', function (#[Header] string $token) {
            return ['token' => $token];
        });

        // Nom explicite, contraintes Svinx appliquées à la valeur.
        $app->get('/api-key', function (#[Header('X-Api-Key', min_length: 8)] string $key) {
            return ['key' => $key];
        });

        // Convention FastAPI : `user_agent` lit l'en-tête `User-Agent`.
        $app->get('/agent', function (#[Header] string $user_agent) {
            return ['agent' => $user_agent];
        });

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/token', [
                'headers' => ['Token' => 'abc'],
            ], json: ['token' => 'abc']),

            self::scenario('GET', '/api-key', [
                'headers' => ['X-Api-Key' => 'secret-123'],
            ], json: ['key' => 'secret-123']),

            self::scenario('GET', '/api-key', [
                'headers' => ['X-Api-Key' => 'court'],
            ], status: 422, contains: 'value_error'),

            self::scenario('GET', '/agent', [
                'headers' => ['User-Agent' => 'EorbahBot/1.0'],
            ], json: ['agent' => 'EorbahBot/1.0']),

            self::scenario('GET', '/token', [], status: 422, contains: 'missing'),
        ];
    }
}
