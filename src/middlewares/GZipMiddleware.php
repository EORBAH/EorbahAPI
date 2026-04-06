<?php

namespace EorBah545\Eorbahapi\middlewares;

class GZipMiddleware {
    public function __construct() {}
    
    public function process($request, $response, $next) {
        
        // GZip middleware implementation
        return $next();
    }
}