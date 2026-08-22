<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\Request;
use Eorbahapi\Response;
use Eorbahapi\EorbahAPI;
use function Eorbahapi\Responses\TwigTemplates;

$app = new EorbahAPI(dev: true);


$app->get('/', function (Request $req, Response $res) {
    return TwigTemplates(templateDir: __DIR__ . '/templates', file: 'index.html.twig', data: ['name' => 'oumar']);
});

$app->run();