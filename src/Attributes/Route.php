<?php

namespace Eorbahapi\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Route {
    public function __construct(
        public readonly string $route,
        public readonly array $methods = ['GET'],
        public readonly array $middlewares = []
    ) {}
}