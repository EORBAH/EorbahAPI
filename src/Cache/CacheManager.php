<?php

namespace Eorbahapi\Cache;

/**
 * Façade de cache de l'application.
 *
 * Elle choisit le magasin selon la configuration passée à `EorbahAPI` :
 *
 *  - `new EorbahAPI()` : aucun cache, `NullCache` (comportement de référence) ;
 *  - `new EorbahAPI(cache: true)` : cache fichier dans `cache/eorbahapi`,
 *    activé pour le schéma OpenAPI et l'accélération des requêtes ;
 *  - `new EorbahAPI(cache: true, cache_dir: '/var/cache/api')` : autre dossier ;
 *  - un magasin injecté (`new CacheManager(store: $store)`), quel qu'il soit.
 *
 *     $app->cache_manager()->remember('rapport-2024', fn () => calculer());
 *
 * Le dossier par défaut est celui du mode « Twig » : les valeurs compilées
 * sont des fichiers PHP, donc relues en un `include` sans recalcul.
 */
final class CacheManager
{
    public const DEFAULT_NAMESPACE = 'eorbahapi';

    private readonly bool $enabled;

    private readonly CacheInterface $store;

    public function __construct(
        bool $enabled = false,
        ?CacheInterface $store = null,
        private readonly ?string $directory = null,
        private readonly int $ttl = 0,
        private readonly string $namespace = self::DEFAULT_NAMESPACE,
    ) {
        $this->enabled = $enabled || $store !== null;
        $this->store = $store ?? ($this->enabled
            ? new FileCache($directory ?? self::defaultDirectory(), $ttl, $namespace)
            : new NullCache());
    }

    /**
     * Dossier de cache par défaut : `<cwd>/cache/eorbahapi`.
     */
    public static function defaultDirectory(): string
    {
        $base = getcwd() ?: sys_get_temp_dir();

        return rtrim($base, '/\\') . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . self::DEFAULT_NAMESPACE;
    }

    /**
     * Cache désactivé (comportement par défaut d'`EorbahAPI`).
     */
    public static function disabled(): self
    {
        return new self();
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function store(): CacheInterface
    {
        return $this->store;
    }

    public function directory(): ?string
    {
        return $this->directory;
    }

    public function ttl(): int
    {
        return $this->ttl;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store->get($key, $default);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->store->set($key, $value, $ttl);
    }

    public function has(string $key): bool
    {
        return $this->store->has($key);
    }

    public function delete(string $key): bool
    {
        return $this->store->delete($key);
    }

    public function clear(): bool
    {
        return $this->store->clear();
    }

    /**
     * Calcule la valeur une seule fois, puis la sert depuis le magasin.
     *
     *     $app->cache_manager()->remember('openapi', fn () => $app->openapi());
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        return $this->store->remember($key, $callback, $ttl);
    }
}
