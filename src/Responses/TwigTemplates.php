<?php

namespace Eorbahapi\Responses;
use Eorbahapi\Templating\TwigTemplates;

function TwigTemplates(
    string $templateDir = 'templates', 
    $file, 
    $data = [], 
    bool $debug = false
): string {
    $routePath = ($rootPath ?? getcwd()).\DIRECTORY_SEPARATOR;
    $twig = new TwigTemplates($templateDir, $routePath . '../cache/twig', $debug);
    $html = $twig->render($file, $data);
    return HTMLResponse($html);
}
