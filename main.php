<?php

require __DIR__ . '/vendor/autoload.php';

use EorBah545\Eorbahapi\EorbahAPI;
use EorBah545\Eorbahapi\StaticFiles;
use EorBah545\Eorbahapi\ExceptionHandlers;
use EorBah545\Eorbahapi\Exceptions\HTTPException;
use EorBah545\Eorbahapi\middlewares\CORSMiddleware;
use EorBah545\Eorbahapi\middlewares\SessionMiddleware;

$app = new EorbahAPI("API principale");

$exceptionHandlers = new ExceptionHandlers();
$exceptionHandlers->overrideExceptionHandlers($app);

$app->mount('/assets', new StaticFiles(__DIR__ . '/public'));

$app->addMiddleware(
    CORSMiddleware::class, [
        'allow_origins' => ['http://localhost:3000'],
        'allow_methods' => ['*'],
        'allow_headers' => ['*']
    ]
);
$app->addMiddleware(SessionMiddleware::class);

$app->get("/", function ($req, $res) {
     $res->HTMLResponse('
     <!DOCTYPE html>
     <html>
         <body>
             <h1>Hello word</h1>
             <script src="/static/index.js"></script>
          </body>
      </html>
     ');
});

$app->get('/secure', function($req, $res) {
    if (!$req->getHeader('Authorization')) {
        throw new HTTPException(401, "Token manquant");
    }
    $res->json(['ok' => true]);
});

$app->run();
