<?php

namespace Eorbahapi\Middlewares;
class BaseHTTPMiddleware {
    public function __construct() {}
    
    public function process($request, $response, $next) {
        
        // BaseHTTP middleware implementation
        return $next();
    }
}