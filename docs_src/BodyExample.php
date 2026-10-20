<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Body;
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;

/**
 * Modèle de corps validé par Svinx\Validator.
 */
final class Product extends BaseModel
{
    #[Field(min_length: 2, max_length: 50)]
    public string $name;

    #[Field(gt: 0)]
    public float $price;

    #[Field(ge: 0, le: 1000)]
    public int $stock = 0;
}

/**
 * Corps de requête : validation d'un modèle complet ou d'un champ unique.
 */
final class BodyExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Tout le corps JSON est validé par le modèle.
        $app->post('/products', function (#[Body] Product $product) {
            return ['product' => $product->model_dump()];
        });

        // Un seul champ du corps, extrait par son nom.
        $app->post('/subscribe', function (#[Body(name: 'email', min_length: 5)] string $email) {
            return ['subscribed' => $email];
        });

        // Le corps complet, brut.
        $app->post('/echo', function (#[Body] array $payload) {
            return ['received' => $payload];
        });

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('POST', '/products', [
                'json' => ['name' => 'Clavier', 'price' => 49.9, 'stock' => 12],
            ], json: ['product' => ['name' => 'Clavier', 'price' => 49.9, 'stock' => 12]]),

            self::scenario('POST', '/products', [
                'json' => ['name' => 'C', 'price' => 0],
            ], status: 422, contains: 'value_error'),

            self::scenario('POST', '/subscribe', [
                'json' => ['email' => 'jean@example.com'],
            ], json: ['subscribed' => 'jean@example.com']),

            self::scenario('POST', '/subscribe', [
                'json' => ['email' => 'x'],
            ], status: 422, contains: 'value_error'),

            self::scenario('POST', '/echo', [
                'json' => ['a' => 1, 'b' => [2, 3]],
            ], json: ['received' => ['a' => 1, 'b' => [2, 3]]]),
        ];
    }
}
