<?php

namespace Eorbahapi\Middlewares;

use Eorbahapi\Request;
use Eorbahapi\Response;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        $response->headers['X-Content-Tye-Options'] = 'nosniff';
        $response->headers['X-Frame-Options'] = 'SAMEORIGIN';
        $response->headers['X-XSS-Protection'] = '1; mode=block';
        $response->headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';

        return $next();
    }
}
