<?php
namespace EorBah545\Eorbahapi\datastructures;

/**
 * Classe Headers - Représentation immuable des en-têtes HTTP
 */
class Headers implements \ArrayAccess, \IteratorAggregate, \Countable {
    private array $headers;
    private array $normalizedKeys;
    
    /**
     * @param array $headers Tableau d'en-têtes [key => value]
     */
    public function __construct(array $headers = []) {
        $this->headers = [];
        $this->normalizedKeys = [];
        
        foreach ($headers as $key => $value) {
            $this->setHeader($key, $value);
        }
    }
    
    /**
     * Normalise la clé (case-insensitive)
     */
    private function normalizeKey(string $key): string {
        return strtolower(str_replace('_', '-', $key));
    }
    
    /**
     * Définit un en-tête (interne, utilisé à la construction)
     */
    private function setHeader(string $key, $value): void {
        $normalizedKey = $this->normalizeKey($key);
        $originalKey = $this->findOriginalKey($key) ?? $key;
        
        $this->headers[$originalKey] = is_array($value) ? $value : [$value];
        $this->normalizedKeys[$normalizedKey] = $originalKey;
    }
    
    /**
     * Trouve la clé originale (respectant la casse d'origine)
     */
    private function findOriginalKey(string $key): ?string {
        $normalizedKey = $this->normalizeKey($key);
        return $this->normalizedKeys[$normalizedKey] ?? null;
    }
    
    /**
     * Récupère un en-tête
     */
    public function get(string $key, $default = null) {
        $originalKey = $this->findOriginalKey($key);
        
        if (!$originalKey || !isset($this->headers[$originalKey])) {
            return $default;
        }
        
        $values = $this->headers[$originalKey];
        return count($values) === 1 ? $values[0] : $values;
    }
    
    /**
     * Récupère tous les valeurs d'un en-tête (tableau)
     */
    public function getAll(string $key): array {
        $originalKey = $this->findOriginalKey($key);
        return $originalKey ? $this->headers[$originalKey] : [];
    }
    
    /**
     * Vérifie si un en-tête existe
     */
    public function has(string $key): bool {
        return $this->findOriginalKey($key) !== null;
    }
    
    /**
     * Récupère tous les en-têtes sous forme de tableau
     */
    public function toArray(): array {
        $result = [];
        foreach ($this->headers as $key => $values) {
            $result[$key] = count($values) === 1 ? $values[0] : $values;
        }
        return $result;
    }
    
    /**
     * Récupère les clés originales
     */
    public function keys(): array {
        return array_keys($this->headers);
    }
    
    /**
     * Interface ArrayAccess - Lecture seule
     */
    public function offsetExists($offset): bool {
        return $this->has($offset);
    }
    
    public function offsetGet($offset): mixed {
        return $this->get($offset);
    }
    
    public function offsetSet($offset, $value): void {
        throw new \RuntimeException('Headers object is immutable');
    }
    
    public function offsetUnset($offset): void {
        throw new \RuntimeException('Headers object is immutable');
    }
    
    /**
     * Interface IteratorAggregate
     */
    public function getIterator(): \Traversable {
        return new \ArrayIterator($this->toArray());
    }
    
    /**
     * Interface Countable
     */
    public function count(): int {
        return count($this->headers);
    }
    
    /**
     * Crée depuis les en-têtes PHP globaux
     */
    public static function fromGlobals(): self {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            $headers = [];
            foreach ($_SERVER as $key => $value) {
                if (strpos($key, 'HTTP_') === 0) {
                    $headerKey = str_replace(' ', '-', ucwords(strtolower(
                        str_replace('_', ' ', substr($key, 5))
                    )));
                    $headers[$headerKey] = $value;
                } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'])) {
                    $headerKey = str_replace('_', '-', $key);
                    $headers[$headerKey] = $value;
                }
            }
        }
        
        return new self($headers);
    }
}