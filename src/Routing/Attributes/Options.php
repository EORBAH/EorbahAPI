<?php

namespace Eorbahapi\Routing\Attributes;

use Attribute;

/**
 * Route HTTP OPTIONS (pré-vol CORS, par exemple).
 *
 *     #[Options('/users')]
 *     public function preflight() { ... }
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Options extends Route
{
    public function __construct(
        string $route,
        array $methods = ['OPTIONS'],
        array $middlewares = []
    ) {
        parent::__construct($route, $methods, $middlewares);
    }
}
