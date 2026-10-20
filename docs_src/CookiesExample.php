<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Cookie;

/**
 * Cookies.
 */
final class CookiesExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        $app->get('/session', function (#[Cookie] string $session_id) {
            return ['session_id' => $session_id];
        });

        // Cookie optionnel, avec contrainte de format.
        $app->get('/theme', function (
            #[Cookie(choices: ['light', 'dark'])] string $theme = 'light'
        ) {
            return ['theme' => $theme];
        });

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/session', [
                'cookies' => ['session_id' => 'abc123'],
            ], json: ['session_id' => 'abc123']),

            self::scenario('GET', '/session', [], status: 422, contains: 'missing'),

            self::scenario('GET', '/theme', [
                'cookies' => ['theme' => 'dark'],
            ], json: ['theme' => 'dark']),

            self::scenario('GET', '/theme', json: ['theme' => 'light']),

            self::scenario('GET', '/theme', [
                'cookies' => ['theme' => 'rose'],
            ], status: 422, contains: 'value_error'),
        ];
    }
}
