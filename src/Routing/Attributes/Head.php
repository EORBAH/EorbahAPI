<?php

namespace Eorbahapi\Routing\Attributes;

use Attribute;

/**
 * Route HTTP HEAD.
 *
 *     #[Head('/health')]
 *     public function health() { ... }
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Head extends Route
{
    public function __construct(
        string $route,
        array $methods = ['HEAD'],
        array $middlewares = []
    ) {
        parent::__construct($route, $methods, $middlewares);
    }
}
