<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Body;
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;
use Svinx\Validator\Validators\EmailStr;

/**
 * Adresse, utilisée comme modèle imbriqué.
 */
final class Address extends BaseModel
{
    #[Field(min_length: 2)]
    public string $city;

    #[Field(pattern: '/^[0-9]{5}$/')]
    public string $zip;
}

/**
 * Client : un modèle qui en contient un autre.
 */
final class Customer extends BaseModel
{
    #[Field(alias: 'full_name', min_length: 2, max_length: 40)]
    public string $name;

    public EmailStr $email;

    #[Field(ge: 18, le: 130)]
    public int $age = 18;

    public Address $address;
}

/**
 * Modèles imbriqués : un modèle dans un autre modèle.
 */
final class NestedBodyExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        $app->post('/customers', function (#[Body] Customer $customer) {
            return ['customer' => $customer->model_dump()];
        });

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('POST', '/customers', [
                'json' => [
                    'full_name' => 'Jean Dupont',
                    'email' => 'jean@example.com',
                    'age' => 30,
                    'address' => ['city' => 'Paris', 'zip' => '75001'],
                ],
            ], json: ['customer' => [
                'full_name' => 'Jean Dupont',
                'email' => 'jean@example.com',
                'age' => 30,
                'address' => ['city' => 'Paris', 'zip' => '75001'],
            ]]),

            self::scenario('POST', '/customers', [
                'json' => [
                    'full_name' => 'Jean Dupont',
                    'email' => 'jean@example.com',
                    'address' => ['city' => 'Paris', 'zip' => '75'],
                ],
            ], status: 422, contains: 'value_error'),
        ];
    }
}
