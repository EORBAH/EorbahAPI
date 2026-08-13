<?php

require __DIR__ . '/../vendor/autoload.php';

use Eorbahapi\EorbahAPI;
use Eorbahapi\Validator\BaseModel;
use Eorbahapi\Validator\Field;

$app = new EorbahAPI('Example Validation');

class ItemRequest extends BaseModel
{
    public string $name;
    public float $price = 0.0;
    public bool $is_offer = false;

    public static function fields(): array
    {
        return [
            'name' => Field::required()->minLength(2),
            'price' => Field::required()->min(0),
            'is_offer' => Field::optional(),
        ];
    }
}

$app->put('/items/{item_id}', function (ItemRequest $item, $item_id) {
    return [
        'item_id' => $item_id,
        'name' => $item->name,
        'price' => $item->price,
        'is_offer' => $item->is_offer,
    ];
});

$app->run();
