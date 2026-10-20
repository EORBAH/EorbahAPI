<?php

namespace Eorbahapi\Middlewares;

use Eorbahapi\Request;
use Eorbahapi\Response;

interface MiddlewareInterface {
    public function __invoke(Request $request, Response $response, callable $next): mixed;
}
