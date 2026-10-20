<?php

namespace Eorbahapi\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Head extends Route {
    public function __construct(
        public readonly string $route,
        public readonly array $methods = ['HEAD'],
        public readonly array $middlewares = []
    ) {}
}