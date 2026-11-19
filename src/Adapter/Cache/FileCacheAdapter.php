<?php

namespace Eorbahapi\Adapter\Cache;

use Eorbahapi\Cache\CacheInterface;
use Eorbahapi\Cache\FileCache;
use Eorbahapi\Contracts\CacheAdapterInterface;

/**
 * Cache fichier (fichiers PHP atomiques) exposé comme adaptateur.
 *
 *     $cache = new FileCacheAdapter(__DIR__ . '/cache/eorbahapi');
 */
final class FileCacheAdapter implements CacheAdapterInterface
{
    private FileCache $cache;

    public function __construct(string $directory, int $ttl = 0, string $namespace = 'eorbahapi')
    {
        $this->cache = new FileCache($directory, $ttl, $namespace);
    }

    public function driver(): string
    {
        return 'file';
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
