<?php

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

use EorBah545\Eorbahapi\Request;
use EorBah545\Eorbahapi\Response;
use EorBah545\Eorbahapi\EorbahAPI;
use EorBah545\Eorbahapi\StaticFiles;
use EorBah545\Eorbahapi\ExceptionHandlers;
use EorBah545\Eorbahapi\middlewares\CORSMiddleware;
use EorBah545\Eorbahapi\middlewares\SessionMiddleware;
use EorBah545\Eorbahapi\middlewares\RateLimitingMiddleware;

use PhoenixAccount\Auth\Router as AuthRouter;
use PhoenixAccount\Users\Router as UsersRouter;

$app = new EorbahAPI("Accounts phoenixshareplus");
$api = new EorbahAPI("Accounts phoenixshareplus API");

// Exception Error
$exceptionHandlers = new ExceptionHandlers();
$exceptionHandlers->overrideExceptionHandlers($api);
$exceptionHandlers->overrideExceptionHandlers($app);

// Session
$app->addMiddleware(SessionMiddleware::class);

// CORS
$app->addMiddleware(
    CORSMiddleware::class,
    [
        'allow_origins'=>[
            "https://accounts.phoenixshareplus.com", // cloudflared tunnel
            "http://localhost:3000", // local apis
            "http://localhost:8000"  // vite frontend
        ],
        'allow_credentials'=>True,
        'allow_methods'=>["*"],
        'allow_headers'=>["*"],
    ]
);

// Ratelimiting

$api->addMiddleware(
    RateLimitingMiddleware::class,
    [
        'max_request'=> 100,
        'timeWindow'=> 60
    ]
);

// API Routes
$api->IncludeRoutes(AuthRouter::class);
$api->IncludeRoutes(UsersRouter::class);

// API strating
$app->mount("/api", $api, "internal api app");


//Frontend Static Files
$app->mount("/static", new StaticFiles("frontend/dist"), "frontend");

$app->get("{full_path:path}", function (Request $req, Response $res) {
    $res->FileResponse("templates/index.html");
});

$app->run();