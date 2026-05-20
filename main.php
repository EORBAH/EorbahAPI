<?php

require __DIR__ . '/vendor/autoload.php';

use EorBah545\Eorbahapi\Request;
use EorBah545\Eorbahapi\Response;
use EorBah545\Eorbahapi\EorbahAPI;
use EorBah545\Eorbahapi\StaticFiles;
use EorBah545\Eorbahapi\ExceptionHandlers;
use EorBah545\Eorbahapi\Exceptions\HTTPException;
use EorBah545\Eorbahapi\security\OAuth2\OAuth2;
/*
$app = new EorbahAPI();
$exceptionHandlers = new ExceptionHandlers();
$exceptionHandlers->overrideExceptionHandlers($app);


$app->run();
*/
use EorBah545\Eorbahapi\security\JWTAuth\JWT;

