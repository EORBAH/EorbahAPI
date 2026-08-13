<?php

namespace Eorbahapi\Security\JWTAuth;

class NotBeforeError extends JsonWebTokenError {
    public $message = 'NotBeforeError';
    public $date;
}