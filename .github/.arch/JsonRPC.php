<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\Request;
use Eorbahapi\EorbahAPI;
use Eorbahapi\StaticFiles;
use Eorbahapi\rpc\JsonRPC;



$app = new EorbahAPI();

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

$app->run();