<?php

namespace Eorbahapi\Dependency;

use Eorbahapi\Request;
use Eorbahapi\Response;

/**
 * Contrat des dépendances résolues par `#[Depends]`.
 *
 * Une classe implémentant cette interface reçoit la requête et la réponse
 * courantes ; la valeur retournée est injectée dans le paramètre.
 *
 *     class CurrentUser implements DependencyInterface
 *     {
 *         public function resolve(Request $request, Response $response): mixed
 *         {
 *             return $request->getHeader('X-User');
 *         }
 *     }
 */
interface DependencyInterface {
    public function resolve(Request $request, Response $response): mixed;
}
