<?php

namespace Eorbahapi\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Post extends Route {
    public function __construct(
        public readonly string $route,
        public readonly array $methods = ['POST'],
        public readonly array $middlewares = []
    ) {}
}