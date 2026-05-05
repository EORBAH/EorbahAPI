<?php

namespace EorBah545\Eorbahapi\security\OAuth2;

class OAuth2PasswordBearer extends OAuth2 {
    public function __construct(
        string $tokenUrl = "token",
        array $scopes = [],
        bool $autoError = true,
        string $schemeName = "OAuth2",
        ?string $description = null
    ) {
        parent::__construct($tokenUrl, $scopes, $autoError, $schemeName, $description);
    }
    
    // Méthodes spécifiques au flow password
    public function validatePasswordGrant(string $username, string $password): array {
        // Simule la validation username/password
        // En réalité, vérifier en base de données
        return [
            'access_token' => $this->generateToken($username),
            'token_type' => 'bearer',
            'expires_in' => 3600
        ];
    }
    
    private function generateToken(string $username): string {
        // En réalité, générer un JWT
        return 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.' . 
               base64_encode(json_encode(['sub' => $username, 'exp' => time() + 3600]));
    }
}