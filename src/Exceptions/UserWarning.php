<?php

namespace Eorbahapi\Exceptions;

class UserWarning
{

    public function __construct(string $message)
    {
        trigger_error(
            $message,
            E_USER_DEPRECATED,
        );
    }
}