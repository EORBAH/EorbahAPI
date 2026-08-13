<?php

namespace EorBah545\Eorbahapi\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Depends {
    public function __construct(
        public ?string $class = null,
        public array $args = []
    ) {}
}