<?php

namespace EorBah545\Eorbahapi\middlewares;

use EorBah545\Eorbahapi\security\RateLimiter;
use EorBah545\Eorbahapi\security\SecurityBase;

class RateLimitingMiddleware {
    private $rate_limiter;
    private $security_base;
    private $max_request;
    private $timeWindow;
    private $route;

    public function __construct(
        array $redis_config = ['host'=> '127.0.0.1', 'port'=> 6379, 'password'=> null],
        int $max_request = 100,
        int $timeWindow = 60,
        string $route = ""
    ) {
        $this->rate_limiter = new RateLimiter($redis_config);
        $this->security_base = new SecurityBase();
        $this->max_request = $max_request;
        $this->timeWindow = $timeWindow;
        $this->route = $route;
    }

    public function process($request, $response, $next) {
        $key = $this->security_base->getClientIP() . "." . $this->route;
        $isExceded = $this->rate_limiter->checkRateLimit($key, $this->max_request, $this->timeWindow);
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