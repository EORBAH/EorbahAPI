<?php

namespace Eorbahapi\Routing;

use Attribute;


#[Attribute(Attribute::TARGET_METHOD)]
class Delete extends Route {
    public function __construct(
        public readonly string $route,
        public readonly array $methods = ['DELETE'],
        public readonly array $middlewares = []
    ) {}
}