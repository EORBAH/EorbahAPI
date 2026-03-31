<?php

namespace EorBah545\Eorbahapi\middlewares;

class AuthMiddleware
{
    private $requiredRole;
    
    public function __construct($requiredRole = null)
    {
        $this->requiredRole = $requiredRole;
    }
    
    public function process($request, $response, $next)
    {
        $token = $request->getHeader('Authorization');
        
        if (!$token) {
            $response->setStatusCode(401);
            $response->json(['error' => 'Token manquant']);
            return false;
        }
        
        // Vérifier le token
        if (!$this->validateToken($token)) {
            $response->setStatusCode(401);
            $response->json(['error' => 'Token invalide']);
            return false;
        }
        
        // Vérifier le rôle si spécifié
        if ($this->requiredRole && !$this->hasRole($this->requiredRole)) {
            $response->setStatusCode(403);
            $response->json(['error' => 'Permission insuffisante']);
            return false;
        }
        
        return $next();
    }
    
    private function validateToken($token) {
        // Logique de validation
        return true;
    }
    
    private function hasRole($role) {
        // Logique de vérification de rôle
        return true;
    }
}