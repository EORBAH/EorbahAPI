<?php

namespace EorBah545\Eorbahapi\Security;

class Depends
{
    public static function resolve(callable $dependency)
    {
        return $dependency();
    }
}