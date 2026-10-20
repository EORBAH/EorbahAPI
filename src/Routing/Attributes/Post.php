<?php

namespace Eorbahapi\Routing\Attributes;

use Attribute;

/**
 * Route HTTP POST.
 *
 *     #[Post('/users')]
 *     public function store() { ... }
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Post extends Route
{
    public function __construct(
        string $route,
        array $methods = ['POST'],
        array $middlewares = []
    ) {
        parent::__construct($route, $methods, $middlewares);
    }
}
