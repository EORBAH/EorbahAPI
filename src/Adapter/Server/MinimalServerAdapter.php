<?php

namespace Eorbahapi\Adapter\Server;

use Eorbahapi\Adapter\Http\RequestAdapter;
use Eorbahapi\Adapter\Http\ResponseAdapter;
use Eorbahapi\Contracts\ESGIAppInterface;
use Eorbahapi\Contracts\ServerAdapterInterface;

/**
 * Adaptateur « une requête » pour les SAPIs classiques (PHP-FPM, `php -S`).
 *
 * Il ne crée pas de serveur persistant : il traite la requête courante à partir
 * des superglobales, confie la réponse à l'application et l'envoie. C'est
 * l'adaptateur par défaut quand aucune extension asynchrone n'est disponible.
 *
 *     (new MinimalServerAdapter())->run($app);
 */
final class MinimalServerAdapter implements ServerAdapterInterface
{
    public function run(ESGIAppInterface $app, string $host = '127.0.0.1', int $port = 8000, bool $reload = false): void
    {
        // $host, $port et $reload ne concernent que les serveurs persistants :
        // ici la requête est déjà celle du SAPI courant.
        $app(RequestAdapter::fromGlobals(), ResponseAdapter::create())->send();
    }
}
