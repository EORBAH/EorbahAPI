<?php

namespace EorBah545\Eorbahapi\middlewares;
class BaseHTTPMiddleware {
    public function __construct() {}
    
    public function process($request, $response, $next) {
        
        // BaseHTTP middleware implementation
        return $next();
    }
}