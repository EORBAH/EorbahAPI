<?php

namespace Eorbahapi\Middlewares;

use Eorbahapi\Contracts\RequestInterface;
use Eorbahapi\Contracts\ResponseInterface;
use Eorbahapi\Contracts\MiddlewareInterface;

class BaseHTTPMiddleware  implements MiddlewareInterface {
    public function __invoke(RequestInterface $request, ResponseInterface $response, callable $next): mixed {
        throw new \RuntimeException('BaseRoute::__invoke must be overridden');
    }
}
