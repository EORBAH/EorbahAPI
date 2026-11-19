<?php

namespace Eorbahapi\Adapter\Cache;

use Eorbahapi\Contracts\CacheAdapterInterface;
use Predis\Client;

/**
 * Cache Redis via Predis, exposé comme adaptateur.
 *
 *     $cache = new RedisCacheAdapter(['host' => '127.0.0.1', 'port' => 6379]);
 *     $cache->remember('rapport', fn () => calculer(), 60);
 *
 * Les valeurs sont sérialisées (`serialize`) pour accepter tableaux, objets et
 * `stdClass` — ce que PHP ne sait pas stocker directement dans Redis.
 */
final class RedisCacheAdapter implements CacheAdapterInterface
{
    private Client $client;

    /**
     * @param array<string, mixed>|object $config Tableau de configuration Predis ou client existant.
     */
    public function __construct(
        array|object $config = [],
        private readonly string $namespace = 'eorbahapi:',
    ) {
        $this->client = is_object($config) ? $config : new Client($config ?: null);
    }

    public function driver(): string
    {
        return 'redis';
    }

    public function client(): Client
    {
        return $this->client;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->client->get($this->namespace . $key);

        if ($value === null) {
            return $default;
        }

        $decoded = @unserialize((string) $value);

        return $decoded === false && (string) $value !== serialize(false) ? $default : $decoded;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $key = $this->namespace . $key;
        $payload = serialize($value);

        $result = $ttl !== null && $ttl > 0
            ? $this->client->setex($key, $ttl, $payload)
            : $this->client->set($key, $payload);

        return (string) $result === 'OK';
    }

    public function has(string $key): bool
    {
        return (bool) $this->client->exists($this->namespace . $key);
    }

    public function delete(string $key): bool
    {
        return (int) $this->client->del([$this->namespace . $key]) > 0;
    }

    public function clear(): bool
    {
        $keys = $this->client->keys($this->namespace . '*');

        if (is_array($keys) && $keys !== []) {
            $this->client->del($keys);
        }

        return true;
    }

    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }
}
