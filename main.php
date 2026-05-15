<?php

require __DIR__ . '/vendor/autoload.php';

use EorBah545\Eorbahapi\Request;
use EorBah545\Eorbahapi\Response;
use EorBah545\Eorbahapi\EorbahAPI;
use EorBah545\Eorbahapi\mcp\EorbahApiMCP;
use EorBah545\Eorbahapi\ExceptionHandlers;
use EorBah545\Eorbahapi\Exceptions\HTTPException;



$app = new EorbahAPI();
$exceptionHandlers = new ExceptionHandlers();
$exceptionHandlers->overrideExceptionHandlers($app);

$mcp = new EorbahApiMCP([
    'name' => 'Mon Serveur MCP',
    'description' => 'Expose des outils via MCP',
]);

$mcp->get('/hello/{name}', function (Request $request, Response $response) {
    $name = $request->params('name');
    $response->json(['message' => "Hello $name"]);
});

$mcp->post('/calculate', function (Request $request, Response $response) {
    $body = $request->body();
    $a = $body['a'] ?? 0;
    $b = $body['b'] ?? 0;
    $response->json(['sum' => $a + $b]);
});

// Monter le serveur MCP sur '/mcp'
$app->mount('/mcp', $mcp);

$app->run();