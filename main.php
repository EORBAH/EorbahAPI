<?php

require __DIR__ . '/vendor/autoload.php';

use EorBah545\Eorbahapi\EorbahAPI;
use EorBah545\Eorbahapi\middlewares\CORSMiddleware;

$app = new EorbahAPI();

$app->addMiddleware(
    CORSMiddleware::class, [
        'allow_origins' => ['http://localhost:3000'],
        'allow_methods' => ['*'],
        'allow_headers' => ['*']
    ]
);


$app->get("/", function ($req, $res) {
    $res->HTMLResponse('
    <!DOCTYPE html>
    <html>
    <body>
        <h1>Test origin</h1>
        <button id="senBtn">Envoyer</button>
        <div id="requestResult"> {{ request_result }} </div>
        <script>
            const sendBtn = document.getElementById("sendBtn")
            const requestResult = document.getElementById("requestResult")
        </script>
    </body>
    </html>
    ');
});

$app->run();