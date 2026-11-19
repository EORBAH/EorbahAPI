<?php

namespace Eorbahapi\Cache;

/**
 * Cache en mémoire, limité à la durée du processus.
 *
 * Testable sans toucher au disque et utile dans un runner persistant
 * (Swoole, RoadRunner, PHP-PM) où le processus traite plusieurs requêtes :
 *
 *     $cache = new ArrayCache();
 *     $cache->remember('schema', fn () => $app->openapi());
 */
final class ArrayCache implements CacheInterface
{
    /** @var array<string, array{value: mixed, expires_at: int|null}> */
    private array $items = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->items[$key]['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $this->items[$key] = [
            'value' => $value,
            'expires_at' => $ttl !== null && $ttl > 0 ? time() + $ttl : null,
        ];

        return true;
    }

    public function has(string $key): bool
    {
        if (!array_key_exists($key, $this->items)) {
            return false;
        }

        $expiresAt = $this->items[$key]['expires_at'];

        if ($expiresAt !== null && $expiresAt <= time()) {
            unset($this->items[$key]);

            return false;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->items = [];

        return true;
    }

    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        if ($this->has($key)) {
            return $this->items[$key]['value'];
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }
}
