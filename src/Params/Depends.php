<?php

namespace Eorbahapi\Params;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Depends {
    public function __construct(
        public string|array|null $dependency = null,
        public array $args = []
    ) {}
}