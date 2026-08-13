<?php

require __DIR__ . '/../vendor/autoload.php';

use Eorbahapi\EorbahAPI;

$app = new EorbahAPI('Example Minimal');

$app->get('/', function () {
    return ['message' => 'Bienvenue sur EorbahAPI'];
});

$app->get('/items/{item_id}', function ($item_id) {
    return ['item_id' => $item_id];
});

$app->run();
