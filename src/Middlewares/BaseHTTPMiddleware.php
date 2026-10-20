<?php

namespace Eorbahapi\Middlewares;

use Eorbahapi\Request;
use Eorbahapi\Response;

class BaseHTTPMiddleware  implements MiddlewareInterface {
    public function __invoke(Request $request, Response $response, callable $next): mixed {
        throw new \RuntimeException('BaseRoute::__invoke must be overridden');
    }
}
