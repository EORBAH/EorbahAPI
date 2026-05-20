<?php

namespace EorBah545\Eorbahapi\templating\tempx\Filter;

interface FilterInterface
{
    public function name(): string;
    public function apply(mixed $value, ?string $param = null): mixed;
}