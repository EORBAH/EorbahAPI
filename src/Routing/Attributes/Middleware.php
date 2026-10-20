<?php

namespace Eorbahapi\Routing\Attributes;

use Attribute;

/**
 * Attache un middleware à une route (méthode) ou à un contrôleur (classe).
 *
 * Remplace le paramètre `middlewares` de [Route] et de ses spécialisations :
 * l'intention est déclarée à côté de la route, et l'attribut est répétable.
 *
 *     use Eorbahapi\Middlewares\RateLimitingMiddleware;
 *
 *     class AuthRouter
 *     {
 *         #[Route('/login', methods: ['GET'])]
 *         #[Middleware(RateLimitingMiddleware::class, args: ['maxRequests' => 3, 'routeSpecific' => true])]
 *         public function login() { return ['status' => 'ok']; }
 *
 *         #[Post('/me')]
 *         public function me() { ... }
 *     }
 *
 * Les `args` sont transmis au constructeur du middleware : un tableau à clés
 * nommées (`['maxRequests' => 3]`) utilise les arguments nommés de PHP, un
 * tableau indexé (`[['host' => 'redis'], 100]`) utilise l'ordre positionnel.
 *
 * Sur une classe, l'attribut s'applique à toutes les routes du contrôleur, et
 * les middlewares de méthode sont exécutés après ceux de la classe.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Middleware
{
    /**
     * @param string $middleware Classe du middleware (instanciée à l'exécution).
     * @param array  $args       Arguments passés au constructeur du middleware.
     */
    public function __construct(
        public readonly string $middleware,
        public readonly array $args = [],
    ) {}
}
