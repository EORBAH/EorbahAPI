<?php

namespace Eorbahapi\Security;

class HTTPAuthorizationCredentials {
    private string $scheme;
    private string $credentials;
    
    public function __construct(string $scheme, string $credentials) {
        $this->scheme = $scheme;
        $this->credentials = $credentials;
    }
    
    public function getScheme(): string {
        return $this->scheme;
    }
    
    public function getCredentials(): string {
        return $this->credentials;
    }
    
    public function isBearer(): bool {
        return strtolower($this->scheme) === 'bearer';
    }
    
    public function isBasic(): bool {
        return strtolower($this->scheme) === 'basic';
    }
    
    public function isDigest(): bool {
        return strtolower($this->scheme) === 'digest';
    }
    
    public function isToken(): bool {
        return strtolower($this->scheme) === 'token';
    }
    
    public function isScheme(string $scheme): bool {
        return strtolower($this->scheme) === strtolower($scheme);
    }
    
    public function toArray(): array {
        return [
            'scheme' => $this->scheme,
            'credentials' => $this->credentials
        ];
    }
    
    public function __toString(): string {
        return $this->scheme . ' ' . $this->credentials;
    }
}