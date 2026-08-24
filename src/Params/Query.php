<?php

namespace Eorbahapi\Params;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Query {
    public function __construct(
        public ?string $name = null,
    ) {}
} 