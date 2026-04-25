<?php

require __DIR__ . '/vendor/autoload.php';

use EorBah545\Eorbahapi\Request;
use EorBah545\Eorbahapi\Response;
use EorBah545\Eorbahapi\EorbahAPI;
use EorBah545\Eorbahapi\StaticFiles;
use EorBah545\Eorbahapi\ExceptionHandlers;
use EorBah545\Eorbahapi\Attributes\Depends;
use EorBah545\Eorbahapi\DependencyInterface;
use EorBah545\Eorbahapi\Exceptions\HTTPException;
use EorBah545\Eorbahapi\middlewares\CORSMiddleware;
use EorBah545\Eorbahapi\middlewares\SessionMiddleware;


$app = new EorbahAPI("API principale");

$exceptionHandlers = new ExceptionHandlers();
$exceptionHandlers->overrideExceptionHandlers($app);

$app->get('/', function(Request $req, Response $res) {
    $res->FileResponse(__DIR__ . '/templates/index.html');
});

$app->mount('/static', new StaticFiles(__DIR__ . '/public'));
$app->run();