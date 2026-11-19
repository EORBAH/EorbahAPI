<?php

namespace Eorbahapi\Contracts;

/**
 * Contrat d'un adaptateur de serveur.
 *
 * Un adaptateur sait démarrer un serveur (ou traiter la requête courante pour
 * les SAPIs classiques) et lui donner l'application à exécuter :
 *
 *     $runner = new ESGIAppRunner(app: $app, adapter: new SwooleServerAdapter());
 *     $runner->run(host: '0.0.0.0', port: 8000, reload: false);
 */
interface ServerAdapterInterface
{
    /**
     * Lance le serveur (ou traite la requête courante) avec l'application.
     *
     * @param string $host   Adresse d'écoute.
     * @param int    $port   Port d'écoute.
     * @param bool   $reload Rechargement à chaud lorsque le serveur le permet.
     */
    public function run(ESGIAppInterface $app, string $host = '127.0.0.1', int $port = 8000, bool $reload = false): void;
}
