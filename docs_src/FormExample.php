<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Form;

/**
 * Données de formulaire (`application/x-www-form-urlencoded`).
 */
final class FormExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Chaque champ est coercé puis validé.
        $app->post('/login', function (
            #[Form(min_length: 3)] string $username,
            #[Form(min_length: 6)] string $password,
        ) {
            return ['username' => $username, 'password_length' => strlen($password)];
        });

        // Un modèle peut recevoir un formulaire complet.
        $app->post('/profile', function (
            #[Form(ge: 18, le: 130)] int $age,
            #[Form(choices: ['fr', 'en'])] string $lang = 'fr',
        ) {
            return ['age' => $age, 'lang' => $lang];
        });

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('POST', '/login', [
                'form' => ['username' => 'oumar', 'password' => 'secret123'],
            ], json: ['username' => 'oumar', 'password_length' => 9]),

            self::scenario('POST', '/login', [
                'form' => ['username' => 'ou', 'password' => 'secret123'],
            ], status: 422, contains: 'value_error'),

            self::scenario('POST', '/profile', [
                'form' => ['age' => '30', 'lang' => 'en'],
            ], json: ['age' => 30, 'lang' => 'en']),

            self::scenario('POST', '/profile', [
                'form' => ['age' => '12'],
            ], status: 422, contains: 'value_error'),
        ];
    }
}
