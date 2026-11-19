<?php

namespace Eorbahapi\Contracts;

use Eorbahapi\Request;
use Eorbahapi\Response;

/**
 * Contrat d'un middleware HTTP.
 *
 * La signature est celle du pipeline actuel d'EorbahAPI : les middlewares
 * reçoivent les objets concrets `Request` et `Response`, et `$next()` renvoie
 * une valeur normalisable en réponse. `Eorbahapi\Middlewares\MiddlewareInterface`
 * en hérite, si bien que tous les middlewares existants respectent déjà ce
 * contrat.
 */
interface MiddlewareInterface
{
    public function __invoke(Request $request, Response $response, callable $next): mixed;
}
