<?php

namespace Eorbahapi\Server;

use Eorbahapi\Adapter\Server\MinimalServerAdapter;
use Eorbahapi\Adapter\Server\SwooleServerAdapter;
use Eorbahapi\Contracts\ESGIAppInterface;
use Eorbahapi\Contracts\ServerAdapterInterface;

/**
 * Point d'entrée serveur d'une application ESGI.
 *
 * Il choisit l'adaptateur (Swoole si disponible, sinon l'adaptateur « une
 * requête ») et lui transmet l'application et les paramètres d'écoute.
 *
 *     $runner = new ESGIAppRunner($app);
 *     $runner->run(host: '0.0.0.0', port: 8000, reload: true);
 *
 *     // ou, explicite :
 *     $runner->run($adapter, host: '127.0.0.1', port: 8000, serverAdapter: new SwooleServerAdapter());
 */
class ESGIAppRunner
{
    public function __construct(
        private ?ESGIAppInterface $app = null,
        private ?ServerAdapterInterface $adapter = null,
    ) {
    }

    /**
     * Lance le serveur.
     *
     * @param ServerAdapterInterface|null $serverAdapter Adaptateur explicite ;
     *                                                   sinon celui du constructeur,
     *                                                   sinon le meilleur disponible.
     */
    public function run(
        ?ESGIAppInterface $app = null,
        string $host = '127.0.0.1',
        int $port = 8000,
        bool $reload = false,
        ?ServerAdapterInterface $serverAdapter = null
    ): void {
        $app ??= $this->app;
        $adapter = $serverAdapter ?? $this->adapter ?? self::defaultAdapter();

        if ($app === null) {
            throw new \InvalidArgumentException('Aucune application à exécuter : passez-la au constructeur ou à run().');
        }

        $adapter->run($app, $host, $port, $reload);
    }

    public function app(?ESGIAppInterface $app = null): self
    {
        if ($app !== null) {
            $this->app = $app;
        }

        return $this;
    }

    public function adapter(?ServerAdapterInterface $adapter = null): self
    {
        if ($adapter !== null) {
            $this->adapter = $adapter;
        }

        return $this;
    }

    public static function defaultAdapter(): ServerAdapterInterface
    {
        return extension_loaded('swoole')
            ? new SwooleServerAdapter()
            : new MinimalServerAdapter();
    }
}
