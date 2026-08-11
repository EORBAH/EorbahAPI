<?php

require __DIR__ . '/../vendor/autoload.php';

use Eorbahapi\EorbahAPI;
use Eorbahapi\StaticFiles;
use Eorbahapi\Response;
use Eorbahapi\ExceptionHandlers;

$app = new EorbahAPI('Example Static Files');
$exceptionHandlers = new ExceptionHandlers();
$exceptionHandlers->overrideExceptionHandlers($app);

$app->mount('/static', new StaticFiles(__DIR__ . '/public', [
    'index' => 'index.html',
    'cache_control' => 'public, max-age=3600',
]));

$app->get('/', function (Response $res) {
    $res->HTMLResponse('<h1>Static Files Demo</h1><p>Visitez <a href="/static/">/static/</a></p>');
});

$app->run();
