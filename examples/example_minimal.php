<?php

require __DIR__ . '/../vendor/autoload.php';

use Eorbahapi\EorbahAPI;
use Eorbahapi\Response;
use Eorbahapi\ExceptionHandlers;

$app = new EorbahAPI('Example Minimal');
$exceptionHandlers = new ExceptionHandlers();
$exceptionHandlers->overrideExceptionHandlers($app);

$app->get('/', function (Response $res) {
    $res->json(['message' => 'Bienvenue sur EorbahAPI']);
});

$app->get('/items/{item_id}', function (Response $res, $item_id) {
    $res->json(['item_id' => $item_id]);
});

$app->run();
