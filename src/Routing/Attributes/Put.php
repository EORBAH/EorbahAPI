<?php

namespace Eorbahapi\Routing\Attributes;

use Attribute;

/**
 * Route HTTP PUT.
 *
 *     #[Put('/users/{id}')]
 *     public function update() { ... }
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Put extends Route
{
    public function __construct(
        string $route,
        array $methods = ['PUT'],
        array $middlewares = []
    ) {
        parent::__construct($route, $methods, $middlewares);
    }
}
