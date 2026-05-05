<?php

namespace EorBah545\Eorbahapi\security;

class Ratelimiting {
    
    /**
     * Vérifie si la requête est autorisée selon la limite définie.
     * Stocke les données en session (démarrage auto).
     *
     * @param string $identifier Identifiant unique (ex: "login_username", "api_userid")
     * @param int $maxRequests Nombre max de requêtes autorisées
     * @param int $period Période en secondes
     * @return bool True = autorisé, False = limité
     */
    public function checkRateLimit(string $identifier, int $maxRequests, int $period): bool
    {
        // Démarrer la session si ce n'est pas déjà fait
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $key = "rate_limit_{$identifier}";
        $now = time();

        // Initialiser ou lire les données depuis $_SESSION
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count' => 1,
                'start_time' => $now
            ];
            return true;
        }

        $data = $_SESSION[$key];

        // Période écoulée ? On réinitialise
        if ($now - $data['start_time'] > $period) {
            $_SESSION[$key] = [
                'count' => 1,
                'start_time' => $now
            ];
            return true;
        }

        // Limite atteinte ?
        if ($data['count'] >= $maxRequests) {
            return false;
        }

        // Sinon, on incrémente
        $_SESSION[$key]['count']++;
        return true;
    }

    /**
     * Exemple de méthode utilitaire pour vérifier si la limite est dépassée
     * sans incrémenter le compteur.
     */
    public function is_requests_limit_exceeded(string $identifier, int $maxRequests, int $period): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $key = "rate_limit_{$identifier}";
        if (!isset($_SESSION[$key])) {
            return false; // pas encore de limite
        }
        $data = $_SESSION[$key];
        $now = time();
        if ($now - $data['start_time'] > $period) {
            return false; // période terminée
        }
        return $data['count'] >= $maxRequests;
    }
}