<?php

namespace Eorbahapi\Cache;

/**
 * Contrat minimal d'un magasin de cache.
 *
 * Il reprend l'essentiel de PSR-16 (`get`, `set`, `has`, `delete`, `clear`)
 * et y ajoute `remember()`, la primitive la plus utile côté framework :
 * calculer une valeur seulement lorsque la clé est absente.
 *
 *     $store->remember('openapi-abc', fn () => (new OpenApiBuilder($app))->build());
 */
interface CacheInterface
{
    /**
     * Lit une valeur ; `$default` est retourné si la clé est absente ou périmée.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Écrit une valeur ; `$ttl` en secondes écrase celui du magasin (0 = infini).
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Indique si la clé existe et n'est pas périmée.
     */
    public function has(string $key): bool;

    /**
     * Supprime une entrée.
     */
    public function delete(string $key): bool;

    /**
     * Vide tout le magasin.
     */
    public function clear(): bool;

    /**
     * Retourne la valeur déjà en cache, sinon exécute `$callback`, stocke le
     * résultat et le retourne.
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed;
}
