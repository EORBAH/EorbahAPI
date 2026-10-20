<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\Params\{Query, Depends, Path, Header, Body};
use Eorbahapi\Templating\TwigTemplates;
use Eorbahapi\{EorbahAPI, Response, StaticFiles};
use Eorbahapi\Responses\{JSONResponse, HTMLResponse, RedirectResponse, EventStreamResponse};

$app = new EorbahAPI(debug: true);

function TemplateResponse() {
   return new TwigTemplates(__DIR__.'/templates');
}

$app->get('/', function(
    #[Depends('TemplateResponse')] $template
) {
    return $template->TemplateResponse('index.html.twig', ["name" => "oumar"]);
});

$app->get('/items/{id}', function (
    #[Path(ge: 1)] int $id,
    #[Query(ge: 1, le: 100)] int $limit = 10,
    #[Header('X-Trace', min_length: 2)] ?string $trace = null,
) {
    return [$id, $limit, $trace];
});

$app->default_route(http_code: 404, handler: function (Response $response) {
    return new JSONResponse(["error" => "404 ROUTE NOT FOUND"]);
});

$app->run();
