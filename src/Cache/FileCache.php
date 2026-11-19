<?php

namespace Eorbahapi\Cache;

/**
 * Cache fichier, sur le modèle du cache de compilation de Twig.
 *
 * Chaque entrée est écrite dans un fichier PHP qui `return` sa charge utile :
 * un `include` est très rapide et le fichier profite de l'opcache du serveur,
 * comme les gabarits Twig compilés. L'écriture est atomique (fichier
 * temporaire puis `rename`), donc deux requêtes concurrentes ne peuvent pas
 * lire une entrée à moitié écrite.
 *
 *     $cache = new FileCache(__DIR__ . '/cache/eorbahapi');
 *     $spec = $cache->remember('openapi-abc', fn () => $builder->build());
 *
 * Seules les valeurs exportables (scalaires, `null`, tableaux imbriqués) sont
 * mises en cache ; un objet est refusé et laissé recalculer par l'appelant.
 */
final class FileCache implements CacheInterface
{
    public function __construct(
        private readonly string $directory,
        private readonly int $ttl = 0,
        private readonly string $namespace = 'eorbahapi',
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->path($key);
        $payload = $this->readPayload($path);

        if (!is_array($payload) || !array_key_exists('value', $payload)) {
            return $default;
        }

        $expiresAt = $payload['expires_at'] ?? null;

        if (is_int($expiresAt) && $expiresAt > 0 && $expiresAt <= time()) {
            @unlink($path);

            return $default;
        }

        return $payload['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        if (!$this->isExportable($value)) {
            return false;
        }

        $ttl = $ttl ?? $this->ttl;
        $payload = [
            'value' => $value,
            'expires_at' => $ttl > 0 ? time() + $ttl : null,
        ];

        try {
            if (!$this->ensureDirectory()) {
                return false;
            }

            $path = $this->path($key);
            $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
            $code = "<?php\n\n// EorbahAPI — cache hors ligne : " . $key . "\n\nreturn "
                . $this->exportValue($payload) . ";\n";

            if (@file_put_contents($temporary, $code) === false) {
                return false;
            }

            if (!@rename($temporary, $path)) {
                @unlink($temporary);

                return false;
            }

            @chmod($path, 0664);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function has(string $key): bool
    {
        $path = $this->path($key);
        $payload = $this->readPayload($path);

        if (!is_array($payload) || !array_key_exists('value', $payload)) {
            return false;
        }

        $expiresAt = $payload['expires_at'] ?? null;

        if (is_int($expiresAt) && $expiresAt > 0 && $expiresAt <= time()) {
            @unlink($path);

            return false;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        $path = $this->path($key);

        return !is_file($path) || @unlink($path);
    }

    public function clear(): bool
    {
        if (!is_dir($this->directory)) {
            return true;
        }

        $pattern = $this->directory . DIRECTORY_SEPARATOR . $this->namespace . '-*';
        $removed = true;

        foreach (glob($pattern . '.php') ?: [] as $file) {
            $removed = @unlink($file) && $removed;
        }

        foreach (glob($pattern . '.tmp') ?: [] as $file) {
            @unlink($file);
        }

        return $removed;
    }

    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $sentinel = new \stdClass();

        $cached = $this->get($key, $sentinel);

        if ($cached !== $sentinel) {
            return $cached;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Chemin du fichier associé à une clé.
     */
    public function path(string $key): string
    {
        return rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR
            . $this->namespace . '-' . hash('sha256', $key) . '.php';
    }

    private function readPayload(string $path): array|false
    {
        if (!is_file($path)) {
            return false;
        }

        try {
            $payload = @include $path;
        } catch (\Throwable) {
            return false;
        }

        return is_array($payload) ? $payload : false;
    }

    private function ensureDirectory(): bool
    {
        if (is_dir($this->directory)) {
            return true;
        }

        @mkdir($this->directory, 0775, true);

        return is_dir($this->directory);
    }

    /**
     * `var_export()` ne sait pas exporter un objet ni une closure : on refuse
     * alors la mise en cache plutôt que d'écrire un fichier impossible à
     * réinclure. Un `stdClass` vide (objet JSON `{}`) est accepté.
     */
    private function isExportable(mixed $value): bool
    {
        if ($value instanceof \stdClass) {
            return (array) $value === [];
        }

        if ($value === null || is_scalar($value)) {
            return true;
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!$this->isExportable($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Représentation PHP d'une valeur, `stdClass` vide compris.
     *
     * @throws \RuntimeException lorsque la valeur n'est pas exportable.
     */
    private function exportValue(mixed $value): string
    {
        if ($value instanceof \stdClass) {
            if ((array) $value !== []) {
                throw new \RuntimeException('stdClass non vide non exportable.');
            }

            return '(object) []';
        }

        if (is_array($value)) {
            if ($value === []) {
                return 'array ()';
            }

            $items = [];

            foreach ($value as $itemKey => $itemValue) {
                $items[] = var_export($itemKey, true) . ' => ' . $this->exportValue($itemValue);
            }

            return "array (\n  " . implode(",\n  ", $items) . ",\n)";
        }

        return var_export($value, true);
    }
}
