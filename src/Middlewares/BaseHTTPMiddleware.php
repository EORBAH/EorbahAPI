<?php

namespace Eorbahapi\Middlewares;
interface BaseHTTPMiddleware {
    public function __construct() {}
    
    public function process($request, $response, $next): mixed;
}