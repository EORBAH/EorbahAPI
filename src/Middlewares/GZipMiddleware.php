<?php

namespace Eorbahapi\Middlewares;

class GZipMiddleware {
    public function __construct() {}
    
    public function process($request, $response, $next) {
        
        // GZip middleware implementation
        return $next();
    }
}