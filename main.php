<?php

require __DIR__ . '/vendor/autoload.php';

use EorBah545\Eorbahapi\Response;
use EorBah545\Eorbahapi\EorbahAPI;
use EorBah545\Eorbahapi\Security\RateLimit;

$app = new EorbahAPI();

$app->disable('X-Powered-By');

$app->get('/me', function (Response $response, RateLimit $rateLimit) {
     $rateLimit->checkRateLimit(
         suffix: '/me',
         maxRequests: 5,
         timeWindow: 60
     );

     $response->json(["message" => "Ceci est une reponse avec des headers"]);
});

$app->run();