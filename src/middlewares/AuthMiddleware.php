<?php

namespace EorBah545\Eorbahapi\middlewares;

class AuthMiddleware {
    public function __construct() {}
    
    public function process($request, $response, $next) {
        
        // Implementation
        return $next();
    }
}