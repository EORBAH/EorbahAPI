<?php

namespace Eorbahapi\Middlewares;

class TrustedHostMiddleware
{
    private array $trustedHosts;

    /**
     * @param array $trustedHosts Liste des hôtes autorisés (ex: ['example.com', 'www.example.com'])
     */
    public function __construct(array $trustedHosts = [])
    {
        $defaults = ['localhost', '127.0.0.1', '::1'];
        $this->trustedHosts = $trustedHosts ?: $defaults;
    }

    /**
     * Traite la requête.
     * Si l'hôte n'est pas dans la liste des hôtes de confiance, retourne 403.
     *
     * @param mixed $request  Objet Request (Eorbahapi\Request)
     * @param mixed $response Objet Response (Eorbahapi\Response)
     * @param callable $next Prochain middleware ou route
     * @return mixed
     */
    public function process($request, $response, $next)
    {
        // Récupération de l'hôte depuis le serveur
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        // Nettoyage : suppression du port éventuel et mise en minuscule
        $host = strtolower(explode(':', $host)[0]);

        // Vérification
        if (!in_array($host, $this->trustedHosts)) {
            return JSONResponse([
                "error" => [
                    "code" =>"403",
                    "message" => "Forbidden: Untrusted host \"$host\""
                ]
            ], 403);
        }

        return $next();
    }
}