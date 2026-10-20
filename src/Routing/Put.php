<?php

namespace Eorbahapi\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Put extends Route {
    public function __construct(
        public readonly string $route,
        public readonly array $methods = ['PUT'],
        public readonly array $middlewares = []
    ) {}
}