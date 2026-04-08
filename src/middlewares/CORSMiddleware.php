<?php

namespace EorBah545\Eorbahapi\middlewares;

class CORSMiddleware {
    private $allowOrigins;
    private $allowCredentials;
    private $allowMethods;
    private $allowHeaders;

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

    public function process($request, $response, $next) {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;

        if ($origin === null) {
            return $next();
        }

        $isOriginAllowed = in_array('*', $this->allowOrigins) || in_array($origin, $this->allowOrigins);

        if (!$isOriginAllowed) {
            $response->status(403);
            $response->json(['error' => 'Origin not allowed']);
            return false;
        }

        if ($this->allowCredentials && in_array('*', $this->allowOrigins)) {
            $response->setHeader('Access-Control-Allow-Origin', $origin);
            $response->setHeader('Access-Control-Allow-Credentials', 'true');
        } elseif (in_array('*', $this->allowOrigins)) {
            $response->setHeader('Access-Control-Allow-Origin', '*');
        } else {
            $response->setHeader('Access-Control-Allow-Origin', $origin);
            if ($this->allowCredentials) {
                $response->setHeader('Access-Control-Allow-Credentials', 'true');
            }
        }

        if (!in_array('*', $this->allowMethods)) {
            $response->setHeader('Access-Control-Allow-Methods', implode(', ', $this->allowMethods));
        } else {
            $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        }

        if (!in_array('*', $this->allowHeaders)) {
            $response->setHeader('Access-Control-Allow-Headers', implode(', ', $this->allowHeaders));
        } else {
            $response->setHeader('Access-Control-Allow-Headers', '*');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $response->setHeader('Access-Control-Max-Age', '86400');
            $response->status(204);
            return false;
        }
        
        return $next();
    }
}