<?php

namespace Eorbahapi\Middlewares;

use Eorbahapi\security\RateLimiter;
use Eorbahapi\Request;

class RateLimitingMiddleware {
    private $rate_limiter;
    private $request;
    private $max_request;
    private $timeWindow;
    private $key;

    public function __construct(
        array $redis_config = ['host'=> '127.0.0.1', 'port'=> 6379, 'password'=> null],
        int $max_request = 100,
        int $timeWindow = 60,
        string $key
    ) {
        $this->rate_limiter = new RateLimiter($redis_config);
        $this->request = new Request();
        $this->max_request = $max_request;
        $this->timeWindow = $timeWindow;
        $this->key = $key ?? $this->request->getClientIP();
    }

    public function process($request, $response, $next) {
        $isExceded = $this->rate_limiter->checkRateLimit($this->key, $this->max_request, $this->timeWindow);
        if(!$isExceded) {
            $response->status(429)->json([
                "response" => [
                    "code" =>"429",
                    "isExceded" => $isExceded,
                    "message" => "ratelimiting exceded"
                ]
            ]);
            return false;
        }

        return $next();
    }
}