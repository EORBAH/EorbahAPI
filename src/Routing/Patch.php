<?php

namespace Eorbahapi\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Patch extends Route {
    public function __construct(
        public readonly string $route,
        public readonly array $methods = ['PATCH'],
        public readonly array $middlewares = []
    ) {}
}