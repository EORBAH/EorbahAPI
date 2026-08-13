<?php

namespace EorBah545\Eorbahapi\Security\OAuth2;

class OAuth2PasswordRequestForm {
    private ?string $username;
    private ?string $password;
    private ?string $scope;
    private ?string $grantType;
    private ?string $clientId;
    private ?string $clientSecret;
    
    public function __construct(
        ?string $username = null,
        ?string $password = null,
        ?string $scope = null,
        string $grantType = "password",
        ?string $clientId = null,
        ?string $clientSecret = null
    ) {
        $this->username = $username;
        $this->password = $password;
        $this->scope = $scope;
        $this->grantType = $grantType;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        
        // Si les données viennent de $_POST
        if ($this->username === null && isset($_POST['username'])) {
            $this->fromPost();
        }
    }
    
    private function fromPost(): void {
        $this->username = $_POST['username'] ?? null;
        $this->password = $_POST['password'] ?? null;
        $this->scope = $_POST['scope'] ?? null;
        $this->grantType = $_POST['grant_type'] ?? 'password';
        $this->clientId = $_POST['client_id'] ?? null;
        $this->clientSecret = $_POST['client_secret'] ?? null;
    }
    
    public function getUsername(): ?string {
        return $this->username;
    }
    
    public function getPassword(): ?string {
        return $this->password;
    }
    
    public function getScope(): ?string {
        return $this->scope;
    }
    
    public function getScopesArray(): array {
        if (!$this->scope) {
            return [];
        }
        return explode(' ', $this->scope);
    }
    
    public function getGrantType(): string {
        return $this->grantType;
    }
    
    public function getClientId(): ?string {
        return $this->clientId;
    }
    
    public function getClientSecret(): ?string {
        return $this->clientSecret;
    }
    
    public function isValid(): bool {
        return $this->username !== null && 
               $this->password !== null && 
               $this->grantType === 'password';
    }
    
    public function validate(): void {
        if (!$this->isValid()) {
            throw new \InvalidArgumentException('Invalid password request form');
        }
    }
    
    public function toArray(): array {
        return [
            'username' => $this->username,
            'password' => $this->password,
            'scope' => $this->scope,
            'grant_type' => $this->grantType,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret
        ];
    }
    
    public static function fromRequest(): self {
        // Récupère depuis $_POST (format application/x-www-form-urlencoded)
        return new self(
            $_POST['username'] ?? null,
            $_POST['password'] ?? null,
            $_POST['scope'] ?? null,
            $_POST['grant_type'] ?? 'password',
            $_POST['client_id'] ?? null,
            $_POST['client_secret'] ?? null
        );
    }
    
    public function __invoke(): array {
        $this->validate();
        return $this->toArray();
    }
}