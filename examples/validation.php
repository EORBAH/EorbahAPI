<?php

require __DIR__ . '/../vendor/autoload.php';

use Eorbahapi\EorbahAPI;
use Eorbahapi\Response;
use Eorbahapi\ExceptionHandlers;
use Eorbahapi\Validation\BaseModel;

$app = new EorbahAPI('Example Validation');
$exceptionHandlers = new ExceptionHandlers();
$exceptionHandlers->overrideExceptionHandlers($app);

class ItemRequest extends BaseModel
{
    public string $name;
    public float $price = 0.0;
    public bool $is_offer = false;
}

$app->put('/items/{item_id}', function (Response $res, ItemRequest $item, $item_id) {
    $res->json([
        'item_id' => $item_id,
        'name' => $item->name,
        'price' => $item->price,
        'is_offer' => $item->is_offer,
    ]);
});

$app->run();
