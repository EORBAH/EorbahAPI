<?php

namespace Eorbahapi\Middlewares;

use Eorbahapi\Request;
use Eorbahapi\Response;
use Eorbahapi\Responses\JSONResponse;

class CORSMiddleware implements MiddlewareInterface {
    private array $allowOrigins;
    private bool $allowCredentials;
    private array $allowMethods;
    private array $allowHeaders;

    public function __construct(
        array $allow_origins = ["*"],
        bool $allow_credentials = false,
        array $allow_methods = ["*"],
        array $allow_headers = ["*"]
    ) {
        $this->allowOrigins = $allow_origins;
        $this->allowCredentials = $allow_credentials;
        $this->allowMethods = $allow_methods;
        $this->allowHeaders = $allow_headers;
    }

    public function __invoke(Request $request, Response $response, callable $next): mixed {
        $origin = $request->getHeader('Origin') ?? $request->server('HTTP_ORIGIN');

        if ($origin === null) {
            return $next();
        }

        $isOriginAllowed = in_array('*', $this->allowOrigins) || in_array($origin, $this->allowOrigins);

        if (!$isOriginAllowed) {
            return new JSONResponse(['error' => 'Origin not allowed'], 403);;
        }

        if ($this->allowCredentials && in_array('*', $this->allowOrigins)) {
            $response->headers['Access-Control-Allow-Origin'] = $origin;
            $response->headers['Access-Control-Allow-Credentials'] ='true';
        } elseif (in_array('*', $this->allowOrigins)) {
            $response->headers['Access-Control-Allow-Origin'] = '*';
        } else {
            $response->headers['Access-Control-Allow-Origin'] = $origin;
            if ($this->allowCredentials) {
                $response->headers['Access-Control-Allow-Credentials'] = 'true';
            }
        }

        if (!in_array('*', $this->allowMethods)) {
            $response->headers['Access-Control-Allow-Methods'] = implode(', ', $this->allowMethods);
        } else {
            $response->headers['Access-Control-Allow-Methods'] = 'GET, POST, PUT, DELETE, PATCH, OPTIONS';
        }

        if (!in_array('*', $this->allowHeaders)) {
            $response->headers['Access-Control-Allow-Headers'] = implode(', ', $this->allowHeaders);
        } else {
            $response->headers['Access-Control-Allow-Headers'] = '*';
        }

        if ($request->method() === 'OPTIONS') {
            $response->headers['Access-Control-Max-Age'] = '86400';
            $response->status = 204;
            return false;
        }

        return $next();
    }
}
