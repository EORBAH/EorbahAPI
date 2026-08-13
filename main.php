<?php

require __DIR__ . '/vendor/autoload.php';


use Eorbahapi\EorbahAPI;
use Eorbahapi\Validator\BaseModel;
use Eorbahapi\Middlewares\SessionMiddleware;
use function Eorbahapi\Responses\JSONResponse;

$app = new EorbahAPI(dev: true);

$app->addMiddleware(SessionMiddleware::class);

class User extends BaseModel {
     public string $name;
     public int $age;
}

$app->put('/users/{userId}', function (User $user, $userId, $q) {
     //return JSONResponse(['id' => $userId, 'name' => $user->name, 'age' => $user->age]);
     return ['id' => $userId, 'name' => $user->name, 'age' => $user->age, "query" => $q];
     //return "Hello world";
});

$app->run();