<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\EorbahAPI;

$app = new EorbahAPI(dev: true);

$app->get('/', function() {
    return new HTTPException(statusCode: 429, message: "Rateli");
});

$app->run();