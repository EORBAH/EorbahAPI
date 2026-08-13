<?php

namespace Eorbahapi\Middlewares;

class SessionMiddleware
{
    private array $options;

    /**
     * @param array $options Options de session (voir php.ini)
     *   - name: nom du cookie de session (défaut: 'PHPSESSID')
     *   - lifetime: durée de vie du cookie (secondes, défaut: 0 = jusqu'à fermeture)
     *   - path: chemin du cookie (défaut: '/')
     *   - domain: domaine du cookie (défaut: null)
     *   - secure: cookie uniquement en HTTPS (défaut: false)
     *   - httponly: cookie inaccessible via JS (défaut: true)
     *   - samesite: 'Lax', 'Strict' ou 'None' (défaut: 'Lax')
     *   - gc_maxlifetime: durée de vie des données sur le serveur (secondes, défaut: 1440)
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'name' => 'PHPSESSID',
            'lifetime' => 0,
            'path' => '/',
            'domain' => null,
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
            'gc_maxlifetime' => 1440
        ];
        $this->options = array_merge($defaults, $options);
    }

    /**
     * Démarre la session sécurisée et l'attache à la requête.
     *
     * @param object $request Objet Request (doit avoir une méthode setSession)
     * @param object $response Objet Response
     * @param callable $next Middleware suivant
     * @return mixed
     */
    public function process($request, $response, $next)
    {
        $this->configureSession();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $response = $next();

        session_write_close();

        return $response;
    }

    /**
     * Configure les paramètres de session (cookie, durée, sécurité).
     */
    private function configureSession(): void
    {
        // Nom du cookie
        session_name($this->options['name']);

        // Durée de vie des données sur le serveur (nettoyage)
        ini_set('session.gc_maxlifetime', $this->options['gc_maxlifetime']);

        // Paramètres du cookie
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => $this->options['lifetime'],
            'path' => $this->options['path'],
            'domain' => $this->options['domain'],
            'secure' => $this->options['secure'],
            'httponly' => $this->options['httponly'],
            'samesite' => $this->options['samesite']
        ]);
    }
}