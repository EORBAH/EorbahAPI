<?php

namespace EorBah545\Eorbahapi\security\OAuth2;

class OAuth2 {
    private string $tokenUrl;
    private array $scopes;
    private bool $autoError;
    private string $schemeName;
    private ?string $description;
    
    public function __construct(
        string $tokenUrl,
        array $scopes = [],
        bool $autoError = true,
        string $schemeName = "OAuth2",
        ?string $description = null
    ) {
        $this->tokenUrl = $tokenUrl;
        $this->scopes = $scopes;
        $this->autoError = $autoError;
        $this->schemeName = $schemeName;
        $this->description = $description;
    }
    
    public function __invoke(): string {
        // Similaire à HTTPBearer mais avec validation OAuth2 spécifique
        $headers = getallheaders();
        $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);
        
        if (!isset($normalizedHeaders['authorization'])) {
            if ($this->autoError) {
                $this->raiseUnauthorizedError('Not authenticated');
            }
            throw new \RuntimeException('Authorization header not found');
        }
        
        $authHeader = $normalizedHeaders['authorization'];
        
        if (!is_string($authHeader) || empty($authHeader)) {
            if ($this->autoError) {
                $this->raiseUnauthorizedError('Invalid authorization header');
            }
            throw new \RuntimeException('Invalid authorization header');
        }
        
        // Vérifier le format "Bearer token"
        if (!preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $matches)) {
            if ($this->autoError) {
                $this->raiseUnauthorizedError('Invalid authorization format');
            }
            throw new \RuntimeException('Invalid authorization format');
        }
        
        $token = $matches[1];
        
        // Validation basique du token
        if (empty($token)) {
            if ($this->autoError) {
                $this->raiseUnauthorizedError('Invalid token');
            }
            throw new \RuntimeException('Invalid token');
        }
        
        return $token;
    }
    
    public function validateScopes(array $requiredScopes = []): callable {
        return function() use ($requiredScopes) {
            $token = $this->__invoke();
            
            // Ici, normalement, vous décoderiez le JWT pour vérifier les scopes
            // Pour l'exemple, on simule une validation
            $tokenScopes = $this->extractScopesFromToken($token);
            
            // Vérifier que tous les scopes requis sont présents
            foreach ($requiredScopes as $scope) {
                if (!in_array($scope, $tokenScopes)) {
                    if ($this->autoError) {
                        $this->raiseForbiddenError("Missing scope: $scope");
                    }
                    throw new \RuntimeException("Missing scope: $scope");
                }
            }
            
            return $token;
        };
    }
    
    public function getTokenUrl(): string {
        return $this->tokenUrl;
    }
    
    public function getScopes(): array {
        return $this->scopes;
    }
    
    public function getSchemeName(): string {
        return $this->schemeName;
    }
    
    public function getDescription(): ?string {
        return $this->description;
    }
    
    public function setAutoError(bool $autoError): void {
        $this->autoError = $autoError;
    }
    
    private function extractScopesFromToken(string $token): array {
        // En réalité, il faudrait décoder le JWT
        // Pour l'exemple, on retourne des scopes simulés
        // Dans une vraie implémentation :
        // $payload = JWT::decode($token, $secret);
        // return $payload['scopes'] ?? [];
        
        return ['read', 'write']; // Simulation
    }
    
    private function raiseUnauthorizedError(string $detail): void {
        $wwwAuthenticate = 'Bearer';
        if ($this->tokenUrl) {
            $wwwAuthenticate .= ' tokenUrl="' . $this->tokenUrl . '"';
        }
        if (!empty($this->scopes)) {
            $wwwAuthenticate .= ' scope="' . implode(' ', $this->scopes) . '"';
        }
        
        header('HTTP/1.1 401 Unauthorized');
        header('WWW-Authenticate: ' . $wwwAuthenticate);
        header('Content-Type: application/json');
        
        echo json_encode([
            'detail' => $detail,
            'error' => 'Unauthorized'
        ]);
        
        exit;
    }
    
    private function raiseForbiddenError(string $detail): void {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/json');
        
        echo json_encode([
            'detail' => $detail,
            'error' => 'Forbidden'
        ]);
        
        exit;
    }
}