<?php

namespace EorBah545\Eorbahapi\middlewares;

class HTTPSRedirectMiddleware {
    public function __construct() {}
    
    public function process($request, $response, $next) {
        
        // HTTPSRedirect middleware implementation
        return $next();
    }
}