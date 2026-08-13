<?php

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use EorBah545\Eorbahapi\security\JWTAuth\JWT;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();


$userData = [
    "id" => "usr_001",
    "sid" => "1"
];

$jwt = new JWT($_ENV['SECRET_KEY'], $_ENV['ALGORITHM']);

$token_pair = $jwt->tokenPair($userData, $_ENV['ACCESS_TOKEN_EXPIRES'], $_ENV['REFRESH_TOKEN_EXPIRES']);

echo "Token pair:\n";
print_r($token_pair);
/*
$access_token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyIjp7ImlkIjoidXNyXzAwMSIsInNpZCI6IjEifSwiZXhwIjoxNzgzNDI3NzA2LCJpYXQiOjE3ODM0MjU5MDZ9.yIKVMln3jrwqC32Z3-WNwxviCGx7zZudhIjdZXquKQY";
$refresh_token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOiJ1c3JfMDAxIiwic2lkIjoiMSIsInR5cGUiOiJyZWZyZXNoIiwiZXhwIjoxODA5MzQ1OTA2LCJpYXQiOjE3ODM0MjU5MDZ9.I0IAMs36Fn2Ff40Sj4YGFugXF3RcZp5Hv5cYtbjxcuQ";
$isExpired = $jwt->isExpired($refresh_token);
$verify = $jwt->verify($refresh_token);

echo "IsExpired: " . ($isExpired ? "true" : "false");
//echo "\nVerify:\n";
//print_r($verify);
*/