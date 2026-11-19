<?php

namespace Eorbahapi\Middlewares;

use Eorbahapi\BackgroundTasks;
use Eorbahapi\Request;
use Eorbahapi\Response;

/**
 * Exécute les `BackgroundTasks` de la requête après le handler.
 *
 * Les tâches tournent après `$next()` — donc après la construction de la
 * réponse — et avant son envoi par le runner. Elles ne peuvent donc plus la
 * modifier, mais elles bénéficient de la même requête résolue.
 */
class BackgroundTasksMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly BackgroundTasks $tasks)
    {
    }

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        try {
            return $next();
        } finally {
            $this->tasks->run();
        }
    }
}
