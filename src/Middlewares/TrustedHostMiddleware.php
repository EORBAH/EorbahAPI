<?php

namespace Eorbahapi\Middlewares;

use Eorbahapi\Request;
use Eorbahapi\Response;
use Eorbahapi\Responses\JSONResponse;

class TrustedHostMiddleware implements MiddlewareInterface
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
     * @param Request $request  Objet Request (Eorbahapi\Request)
     * @param Response $response Objet Response (Eorbahapi\Response)
     * @param callable $next Prochain middleware ou route
     * @return mixed
     */
    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        // Hôte de la requête (en-tête Host recopié dans $_SERVER par le SAPI,
        // ou fourni par une requête injectée).
        $host = (string) ($request->getHeader('Host')
            ?? $request->server('HTTP_HOST')
            ?? $request->server('SERVER_NAME')
            ?? '');
        // Nettoyage : suppression du port éventuel et mise en minuscule
        $host = strtolower(explode(':', $host)[0]);

        // Vérification
        if (!in_array($host, $this->trustedHosts)) {
            return new JSONResponse([
                "error" => [
                    "code" =>"403",
                    "message" => "Forbidden: Untrusted host \"$host\""
                ]
            ], 403);
        }

        return $next();
    }
}
