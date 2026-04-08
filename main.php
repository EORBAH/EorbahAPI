<?php

require __DIR__ . '/vendor/autoload.php';

use EorBah545\Eorbahapi\EorbahAPI;
use EorBah545\Eorbahapi\middlewares\CORSMiddleware;
use EorBah545\Eorbahapi\middlewares\SessionMiddleware;

$app = new EorbahAPI();

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
          </body>
      </html>
     ');
});

$app->run();
