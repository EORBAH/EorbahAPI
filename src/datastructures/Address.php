<?php

namespace EorBah545\Eorbahapi\datastructures;

/**
 * Classe Address - Représente une adresse IP et port
 */
class Address implements \JsonSerializable
{
    private ?string $host;
    private ?int $port;
    
    public function __construct(?string $host = null, ?int $port = null)
    {
        $this->host = $host;
        $this->port = $port;
    }
    
    /**
     * Récupère l'hôte (adresse IP)
     */
    public function getHost(): ?string
    {
        return $this->host;
    }
    
    /**
     * Récupère le port
     */
    public function getPort(): ?int
    {
        return $this->port;
    }
    
    /**
     * Vérifie si l'adresse est complète
     */
    public function isComplete(): bool
    {
        return $this->host !== null && $this->port !== null;
    }
    
    /**
     * Représentation sous forme de string
     */
    public function __toString(): string
    {
        if ($this->host === null && $this->port === null) {
            return 'unknown';
        }
        
        if ($this->port === null) {
            return $this->host;
        }
        
        return $this->host . ':' . $this->port;
    }
    
    /**
     * Interface JsonSerializable
     */
    public function jsonSerialize(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'address' => (string) $this
        ];
    }
    
    /**
     * Crée depuis la variable $_SERVER
     */
    public static function fromServerGlobal(): self
    {
        $host = null;
        $port = null;
        
        // Récupération de l'IP client
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $host = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $host = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $host = $_SERVER['REMOTE_ADDR'];
        }
        
        // Récupération du port
        if (!empty($_SERVER['REMOTE_PORT'])) {
            $port = (int) $_SERVER['REMOTE_PORT'];
        }
        
        return new self($host, $port);
    }
    
    /**
     * Déstructure comme un tuple (comme Python)
     */
    public function destructure(): array
    {
        return [$this->host, $this->port];
    }
}