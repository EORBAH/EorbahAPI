## Gestion des routes pour une single page Application


```php
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

$app = new EorbahAPI();

$exceptionHandlers = new ExceptionHandlers();
$exceptionHandlers->overrideExceptionHandlers($app);

// manifest.json, 
$app->mount("/static", new StaticFiles("frontend/dist/"), "frontend");

// index.html
$app->get("{full_path:path}", function (Request $req, Response $res) {
    $res->HTMLResponse('
    <!DOCTYPE html>
    <html>
    <body>
        <h1>Hello world</h1>
    </body>
    </html>
    ');
});

$app->run();
```