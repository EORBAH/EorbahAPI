<?php

namespace EorBah545\Eorbahapi\templating\tempx\Filter;

class UppercaseFilter implements FilterInterface
{
    public function name(): string { return 'uppercase'; }
    public function apply(mixed $value, ?string $param = null): mixed
    {
        return is_string($value) ? strtoupper($value) : $value;
    }
}