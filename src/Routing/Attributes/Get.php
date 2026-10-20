<?php

namespace Eorbahapi\Routing\Attributes;

use Attribute;

/**
 * Route HTTP GET.
 *
 *     #[Get('/users/{id}')]
 *     public function show() { ... }
 *
 *     #[Get('/users', middlewares: [AuthMiddleware::class])] // hérité
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Get extends Route
{
    public function __construct(
        string $route,
        array $methods = ['GET'],
        array $middlewares = []
    ) {
        parent::__construct($route, $methods, $middlewares);
    }
}
