<?php

namespace Eorbahapi\Middlewares;

use Eorbahapi\Security\RateLimiter;
use Eorbahapi\Request;
use function Eorbahapi\Responses\JSONResponse;

class RateLimitingMiddleware
{
    private RateLimiter $rateLimiter;
    private int $maxRequests;
    private int $timeWindow;
    private bool $routeSpecific;
    private ?string $customPrefix;

    /**
     * @param array $redis_config Configuration Redis (host, port, password)
     * @param int $maxRequests Nombre maximum de requêtes autorisées
     * @param int $timeWindow Fenêtre de temps en secondes
     * @param bool $routeSpecific Si true, la clé inclut le chemin de la route (recommandé pour un usage par route)
     * @param string|null $customPrefix Préfixe personnalisé pour la clé (surcharge tout)
     */
    public function __construct(
        array $redis_config = ['host' => '127.0.0.1', 'port' => 6379, 'password' => null],
        int $maxRequests = 100,
        int $timeWindow = 60,
        bool $routeSpecific = true,
        ?string $customPrefix = null
    ) {
        $this->rateLimiter = new RateLimiter($redis_config);
        $this->maxRequests = $maxRequests;
        $this->timeWindow = $timeWindow;
        $this->routeSpecific = $routeSpecific;
        $this->customPrefix = $customPrefix;
    }

    public function process($request, $response, $next) {
        // Construction de la clé
        if ($this->customPrefix !== null) {
            $key = $this->customPrefix;
        } else {
            $key = $request->getClientIP();
            if ($this->routeSpecific) {
                $key .= ':' . $request->path();
                $key .= ':' . $request->method();
            }
        }

        $isAllowed = $this->rateLimiter->checkRateLimit($key, $this->maxRequests, $this->timeWindow);
        if (!$isAllowed) {
            
            return JSONResponse([
                'error' => [
                    'code' => '429',
                    'message' => 'Rate limit exceeded',
                    'limit' => $this->maxRequests,
                    'window' => $this->timeWindow,
                ]
            ], 429);
        }

        return $next();
    }
}