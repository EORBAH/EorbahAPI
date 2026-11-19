<?php

namespace Eorbahapi\Contracts;

/**
 * Contrat d'un adaptateur de cache.
 *
 * Il ne redéfinit pas les opérations : un adaptateur (Redis, mémoire, fichier)
 * implémente directement `Eorbahapi\Cache\CacheInterface`. Ce contrat ajoute le
 * nom du pilote, utile pour le diagnostic et la configuration.
 */
interface CacheAdapterInterface extends \Eorbahapi\Cache\CacheInterface
{
    /** Nom du pilote (`redis`, `file`, `memory`…). */
    public function driver(): string;
}
