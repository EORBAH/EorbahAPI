<?php

/**
 * Registre des exemples de la documentation.
 *
 * Chaque fichier `*Example.php` de `docs_src/` décrit une application et les
 * scénarios qui prouvent son comportement. `tests/ExamplesTest.php` charge ce
 * registre et exécute tout : la documentation et le framework ne peuvent pas
 * diverger.
 *
 * Ce fichier charge aussi les fichiers qui déclarent plusieurs classes
 * (contrôleurs, modèles, middlewares d'exemple), chose que la PSR-4 ne sait
 * pas faire.
 */

$files = glob(__DIR__ . '/*.php') ?: [];

foreach ($files as $file) {
    if (basename($file) === 'registry.php') {
        continue;
    }

    require_once $file;
}

return [
    \Eorbahapi\Examples\FirstStepsExample::class,
    \Eorbahapi\Examples\RoutingExample::class,
    \Eorbahapi\Examples\PathParamsExample::class,
    \Eorbahapi\Examples\QueryParamsExample::class,
    \Eorbahapi\Examples\QueryValidationsExample::class,
    \Eorbahapi\Examples\BodyExample::class,
    \Eorbahapi\Examples\NestedBodyExample::class,
    \Eorbahapi\Examples\FormExample::class,
    \Eorbahapi\Examples\FilesExample::class,
    \Eorbahapi\Examples\HeadersExample::class,
    \Eorbahapi\Examples\CookiesExample::class,
    \Eorbahapi\Examples\ResponsesExample::class,
    \Eorbahapi\Examples\DependenciesExample::class,
    \Eorbahapi\Examples\SecurityExample::class,
    \Eorbahapi\Examples\MiddlewaresExample::class,
    \Eorbahapi\Examples\ControllersExample::class,
    \Eorbahapi\Examples\StaticFilesExample::class,
    \Eorbahapi\Examples\TemplatingExample::class,
    \Eorbahapi\Examples\TestingExample::class,
    \Eorbahapi\Examples\RunnersExample::class,
    \Eorbahapi\Examples\ErrorsExample::class,
    \Eorbahapi\Examples\RpcExample::class,
];
