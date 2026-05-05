<?php

require __DIR__ . "/vendor/autoload.php";

use Eorbah545\Eorbahapi\EorbahAPI;
use Eorbah545\Eorbahapi\staticfiles\StaticFiles;
use Eorbah545\Eorbahapi\middleware\CORSMiddleware;
use Eorbah545\Eorbahapi\responses\StreamingResponse;

use Eorbah545\App\routes\Login;
use Eorbah545\App\routes\RouteAPI;

$app = new EorbahAPI();

$app->addMiddleware(
    CORSMiddleware::class, [
        'allow_origins' => ['http://localhost:3000', 'http://localhost'],
        'allow_methods' => ['*'],
        'allow_headers' => ['*']
    ]
);

$app->mount("/static", new StaticFiles("frontend/static"), "static");

$app->get("/sw.js", function ($req, $res) {
    $res->FileResponse("frontend/sw.js");
});

$app->get("/{full_path:path}", function ($req, $res) {
    $res->FileResponse("frontend/index.html");
});

# streaming SSE
function event_generator() {
    $counter = 1;
    while ($counter <= 10) {
        yield "data: Message numéro {$counter}\n\n";
        $counter++;
        sleep(1);
    }
    yield "event: end\ndata: stream_ended\n\n";
}
/*
* @return void
* @function StreaminResponse
* @Description
*----
function StreamingResponse(callable $callback, $ContentType = 'text/event-stream'): void {
    header("Content-Type: $ContentType");
    header('Cache-Control: no-cache');

    $gen = $callback();
    foreach ($gen as $event) {
        echo $event;
        ob_flush();
        flush();
    }
}
* ----
*/
# -- streaming ----
$app->get('/sse', function () {
    StreamingResponse('event_generator');
});

# inclusion de routes
$app->IncludeRoute(Login::class);
$app->IncludeRoutes(RouteAPI::class);
$app->run();