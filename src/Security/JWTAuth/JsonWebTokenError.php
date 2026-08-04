<?php

namespace EorBah545\Eorbahapi\Security\JWTAuth;

use Exception;

class JsonWebTokenError extends Exception {
    public $message = 'JsonWebTokenError';
}