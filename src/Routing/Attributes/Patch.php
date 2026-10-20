<?php

namespace Eorbahapi\Routing\Attributes;

use Attribute;

/**
 * Route HTTP PATCH.
 *
 *     #[Patch('/users/{id}')]
 *     public function patch() { ... }
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Patch extends Route
{
    public function __construct(
        string $route,
        array $methods = ['PATCH'],
        array $middlewares = []
    ) {
        parent::__construct($route, $methods, $middlewares);
    }
}
