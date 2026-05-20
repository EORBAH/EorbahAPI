<?php

namespace EorBah545\Eorbahapi\templating\tempx;

class CacheManager
{
    private string $cacheDir;
    private bool $enabled;
    private array $ignoredKeys;

    public function __construct(string $cacheDir, bool $enabled = true, array $ignoredKeys = [])
    {
        $this->cacheDir = rtrim($cacheDir, '/');
        $this->enabled = $enabled;
        $this->ignoredKeys = $ignoredKeys;
    }

    public function get(string $template, array $data, string $version, callable $compiler): string
    {
        if (!$this->enabled) {
            return $compiler();
        }

        $cacheKey = $this->generateKey($template, $data, $version);
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.cache';

        if (file_exists($cacheFile)) {
            return file_get_contents($cacheFile);
        }

        $result = $compiler();

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        file_put_contents($cacheFile, $result);

        return $result;
    }

    private function generateKey(string $template, array $data, string $version): string
    {
        // Nettoyer les données : exclure les clés ignorées, puis trier récursivement
        $cleanData = $data;
        foreach ($this->ignoredKeys as $key) {
            unset($cleanData[$key]);
        }
        // Tri récursif pour garantir un ordre stable
        $this->recursiveKsort($cleanData);

        return md5($template . json_encode($cleanData, JSON_UNESCAPED_UNICODE) . $version);
    }

    private function recursiveKsort(&$array): void
    {
        if (!is_array($array)) return;
        ksort($array);
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->recursiveKsort($value);
            }
        }
    }
}