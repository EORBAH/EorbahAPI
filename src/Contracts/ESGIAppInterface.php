<?php

namespace Eorbahapi\Contracts;

/**
 * Application appelable (EorbahAPI Server Gateway Interface).
 *
 * C'est la porte d'entrée commune aux adaptateurs de serveur : une application
 * reçoit une requête et une réponse, remplit la réponse et la retourne. Le
 * serveur décide ensuite comment l'envoyer (PHP-FPM, Swoole, tests…).
 *
 *     $app = new EorbahAPI();
 *     $response = $app($request, $response);
 */
interface ESGIAppInterface
{
    public function __invoke(RequestInterface $request, ResponseInterface $response): ResponseInterface;
}
