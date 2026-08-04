<?php

namespace EorBah545\Eorbahapi\Middlewares;

class AuthMiddleware {
    public function __construct() {}
    
    public function process($request, $response, $next) {
        
        // Implementation
        return $next();
    }
}