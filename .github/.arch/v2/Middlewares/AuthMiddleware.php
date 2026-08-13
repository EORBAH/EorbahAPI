<?php

namespace Eorbahapi\Middlewares;

class AuthMiddleware {
    public function __construct() {}
    
    public function process($request, $response, $next) {
        
        // Implementation
        return $next();
    }
}