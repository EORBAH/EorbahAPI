<?php

namespace Eorbahapi\Security\JWTAuth;

class TokenExpiredError extends JsonWebTokenError {
    public $message = 'TokenExpiredError';
    public $expiredAt;
}