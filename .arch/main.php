<?php

require __DIR__ . '/vendor/autoload.php';

use EorBah545\Eorbahapi\Request;
use EorBah545\Eorbahapi\Response;
use EorBah545\Eorbahapi\EorbahAPI;
use EorBah545\Eorbahapi\StaticFiles;
use EorBah545\Eorbahapi\rpc\JsonRPC;
use EorBah545\Eorbahapi\ExceptionHandlers;
use EorBah545\Eorbahapi\Exceptions\HTTPException;



$app = new EorbahAPI();
$exceptionHandlers = new ExceptionHandlers();
$exceptionHandlers->overrideExceptionHandlers($app);



$app->get('/api/{version}', function(Response $res, Request $req, $version) {
    $params = $req->params();
    $res->json([
      "response" => "ok",
      "version" => $version,
      "params" => $params
    ]);
});

// Enregistrement des méthodes
$rpc = new JsonRPC();
$rpc->add_method('addition', function (int $a, int $b) {
    return $a + $b;
});

$rpc->add_method('getUser', function (Request $req, int $userId) {
    // $req est injecté automatiquement
    return ['id' => $userId, 'name' => "User $userId"];
});

// Dans EorbahAPI, on monte le serveur sur une route
$app->mount('/rpc', $rpc);

$app->mount("/docs", new StaticFiles("docs/"));

$app->run();