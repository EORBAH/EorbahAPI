<?php

namespace EorBah545\Eorbahapi\security;
class Ratelimiting {
    private $rateLimits = [];
    
    /**
     * Summary of checkRateLimit
     * @param string $identifier
     * @param int $maxRequests
     * @param int $period
     * @return bool
     */
    public function checkRateLimit(string $identifier, int $maxRequests, int $period): bool
    {
        $key = "rate_limit_{$identifier}";
        $now = time();

        if (!isset($this->rateLimits[$key])) {
            $this->rateLimits[$key] = [
                'count' => 1,
                'start_time' => $now
            ];
            return true;
        }

        if ($now - $this->rateLimits[$key]['start_time'] > $period) {
            $this->rateLimits[$key] = [
                'count' => 1,
                'start_time' => $now
            ];
            return true;
        }

        if ($this->rateLimits[$key]['count'] >= $maxRequests) {
            return false;
        }

        $this->rateLimits[$key]['count']++;
        return true;
    }

    public function is_requests_limit_execeded() {}
}