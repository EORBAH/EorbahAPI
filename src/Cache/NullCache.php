<?php

namespace Eorbahapi\Cache;

/**
 * Cache désactivé : chaque lecture manque et `remember()` exécute toujours le
 * calcul. C'est le magasin utilisé par défaut (`new EorbahAPI()`), ce qui
 * garantit un comportement identique avec ou sans cache du point de vue du
 * code applicatif.
 */
final class NullCache implements CacheInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return false;
    }

    public function has(string $key): bool
    {
        return false;
    }

    public function delete(string $key): bool
    {
        return true;
    }

    public function clear(): bool
    {
        return true;
    }

    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        return $callback();
    }
}
