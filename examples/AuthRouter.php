<?php

namespace Eorbahapi\Examples;

use Eorbahapi\Attributes\Route;
use Eorbahapi\Middlewares\RateLimitingMiddleware;

class AuthRouter {

    #[Route('/login', methods: ['GET'],
        middlewares: [
            [RateLimitingMiddleware::class, 'maxRequests' => 3, 'routeSpecific' => true]
        ]
    )]
    public function login() {
        return ["status" => "ok"];
    }
}