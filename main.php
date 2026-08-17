<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\EorbahAPI;
use Eorbahapi\Exceptions\HTTPException;

$app = new EorbahAPI(dev: true);

$app->get('/', function() {
    return new HTTPException(statusCode: 429, message: "Rateli");
});

$app->run();