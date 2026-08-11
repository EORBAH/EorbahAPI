<?php

namespace Eorbahapi\Security;

use Eorbahapi\Security\RateLimiter;
use Eorbahapi\Request;
use Eorbahapi\Response;

class RateLimit
{

    private $rate_limiter;
    private $request;
    private $response;

    public function __construct()
    {
        $this->rate_limiter = new RateLimiter(['host' => '127.0.0.1', 'port' => 6379, 'password' => null]);
        $this->request = new Request();
        $this->response = new Response();
    }

    /**
     * Vérifie si la requête est autorisée selon la limite définie.
     * Stocke les données en session (démarrage auto).
     *
     * @param int $maxRequests Nombre max de requêtes autorisées
     * @param int $timeWindow Période en secondes
     * @return bool True = autorisé, False = limité
     */
    public function checkRateLimit(string $suffix = '', string $maxRequests, int $timeWindow, bool $default = true): bool {
        $key = $this->request->getClientIP() . '-' . $suffix;
        $isExceded = $this->rate_limiter->checkRateLimit($key, $maxRequests, $timeWindow);

        if(!$isExceded && $default) {
            $this->response->status(429)->json([
                "response" => [
                    "code" =>"429",
                    "isExceded" => $isExceded,
                    "message" => "ratelimiting exceded"
                ]
            ]);
            exit;
        }

        if(!$isExceded && !$default) {
            return true;
        } else {
            return false;
        }
    }

}