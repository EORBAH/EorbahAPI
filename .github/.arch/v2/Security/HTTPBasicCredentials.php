<?php

namespace Eorbahapi\Security;

class HTTPBasicCredentials {
    private string $username;
    private string $password;
    
    public function __construct(string $username, string $password) {
        $this->username = $username;
        $this->password = $password;
    }
    
    public function getUsername(): string {
        return $this->username;
    }
    
    public function getPassword(): string {
        return $this->password;
    }
    
    public function toArray(): array {
        return [
            'username' => $this->username,
            'password' => $this->password
        ];
    }
    
    public function toBase64(): string {
        return base64_encode($this->username . ':' . $this->password);
    }
    
    public function __toString(): string {
        return $this->username . ':' . $this->password;
    }
    
    public static function fromBase64(string $base64Credentials): self {
        $decoded = base64_decode($base64Credentials, true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid base64 credentials');
        }
        
        $parts = explode(':', $decoded, 2);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Invalid credentials format');
        }
        
        return new self($parts[0], $parts[1]);
    }
    
    public static function fromArray(array $data): self {
        if (!isset($data['username']) || !isset($data['password'])) {
            throw new \InvalidArgumentException('Missing username or password in array');
        }
        
        return new self($data['username'], $data['password']);
    }
}