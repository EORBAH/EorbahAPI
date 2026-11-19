<?php

namespace Eorbahapi\Adapter\Cache;

use Eorbahapi\Cache\ArrayCache;
use Eorbahapi\Cache\CacheInterface;
use Eorbahapi\Contracts\CacheAdapterInterface;

/**
 * Cache en mémoire du processus, exposé comme adaptateur.
 *
 *     $cache = new MemoryCacheAdapter();
 *     $cache->remember('rapport', fn () => calculer());
 */
final class MemoryCacheAdapter implements CacheAdapterInterface
{
    private ArrayCache $cache;

    public function __construct(?ArrayCache $cache = null)
    {
        $this->cache = $cache ?? new ArrayCache();
    }

    public function driver(): string
    {
        return 'memory';
    }

    public function unwrap(): CacheInterface
    {
        return $this->cache;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get($key, $default);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->cache->set($key, $value, $ttl);
    }

    public function has(string $key): bool
    {
        return $this->cache->has($key);
    }

    public function delete(string $key): bool
    {
        return $this->cache->delete($key);
    }

    public function clear(): bool
    {
        return $this->cache->clear();
    }

    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        return $this->cache->remember($key, $callback, $ttl);
    }
}
