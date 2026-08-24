<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\Params\{Query, Depends};
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

$app->get('/re', function () {
    return new RedirectResponse('/');
});

$app->post('/items', function (string $q, Response $response) {
    $response->headers['Custom-Header'] = 'Custom-value';
    $response->status = 404;
    return ['query' => $q];
});

$app->get('/events', function() {
    $generator = function () {
        foreach (['bonjour', 'monde', 'Moi'] as $message) {
            yield "data: $message\n\n";
            sleep(1);
        }
    };

    return new EventStreamResponse($generator()); 
});

$app->default_route(http_code: 404, handler: function (Response $response) {
    return new JSONResponse(["error" => "404 ROUTE NOT FOUND"]);
});
 
$app->run();