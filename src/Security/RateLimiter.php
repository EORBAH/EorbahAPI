<?php

namespace Eorbahapi\Security;

use Predis\Client as RedisClient;

class RateLimiter
{
    private RedisClient $redis;

    /**
     * Constructeur : établit la connexion à Redis.
     * @param mixed $redis_config
     */
    public function __construct($redis_config = ['host'=> '127.0.0.1', 'port'=> 6379, 'password'=> null])
    {
        $this->redis = new RedisClient($redis_config);
    }

    /**
     * Vérifie si une action est autorisée selon la limite définie.
     * Utilise le Fixed Window Counter, un algorithme simple et efficace.
     *
     * @param string $key L'identifiant unique pour cette limite (ex: 'ip:192.168.1.1').
     * @param int $maxRequests Nombre maximum de requêtes autorisées.
     * @param int $timeWindow Période de temps en secondes.
     * @return bool Retourne true si l'action est autorisée, false si la limite est dépassée.
     */
    public function checkRateLimit(string $key, int $maxRequests, int $timeWindow): bool
    {
        
        // 1. Déterminer la clé Redis et la fenêtre de temps actuelle
        $currentWindow = floor(time() / $timeWindow);
        $redisKey = "rate_limit:{$key}:{$currentWindow}";

        // 2. Incrémentation atomique et gestion de l'expiration
        $count = $this->redis->incr($redisKey);
        
        if ($count === 1) {
            // Si c'est la première requête dans cette fenêtre, on définit l'expiration.
            $this->redis->expire($redisKey, $timeWindow);
        }
        
        // 3. Vérifier si la limite est dépassée
        return $count <= $maxRequests;
    }
}