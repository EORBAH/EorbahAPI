<?php

namespace Eorbahapi\Routing\Attributes;

use Attribute;

/**
 * Route HTTP DELETE.
 *
 *     #[Delete('/users/{id}')]
 *     public function destroy() { ... }
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Delete extends Route
{
    public function __construct(
        string $route,
        array $methods = ['DELETE'],
        array $middlewares = []
    ) {
        parent::__construct($route, $methods, $middlewares);
    }
}
