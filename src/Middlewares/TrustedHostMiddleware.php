<?php

namespace Eorbahapi\Middlewares;

class TrustedHostMiddleware {
    public function __construct() {}
    
    public function process($request, $response, $next) {
        
        // TrustedHost middleware implementation
        return $next();
    }
}