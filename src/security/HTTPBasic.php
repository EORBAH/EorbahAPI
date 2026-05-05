<?php

namespace EorBah545\Eorbahapi\Security;

class HTTPBasic {
    private bool $autoError;
    private string $scheme;
    private string $realm;
    
    public function __construct(
        bool $autoError = true,
        string $scheme = "Basic",
        string $realm = ""
    ) {
        $this->autoError = $autoError;
        $this->scheme = $scheme;
        $this->realm = $realm;
    }
    
    public function __invoke(): HTTPAuthorizationCredentials {
        $headers = getallheaders();
        $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);
        
        if (!isset($normalizedHeaders['authorization'])) {
            if ($this->autoError) {
                $this->raiseUnauthorizedError('Authorization header missing');
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
        
        // Parse "Basic base64(username:password)"
        $parts = explode(' ', $authHeader, 2);
        
        if (count($parts) !== 2) {
            if ($this->autoError) {
                $this->raiseUnauthorizedError('Invalid authorization format');
            }
            throw new \RuntimeException('Invalid authorization format');
        }
        
        $scheme = $parts[0];
        $credentials = $parts[1];
        
        // Vérifier que c'est bien du Basic
        if (strtolower($scheme) !== 'basic') {
            if ($this->autoError) {
                $this->raiseUnauthorizedError('Invalid authorization scheme');
            }
            throw new \RuntimeException('Invalid authorization scheme');
        }
        
        // Décoder les credentials base64
        $decoded = base64_decode($credentials, true);
        if ($decoded === false) {
            if ($this->autoError) {
                $this->raiseUnauthorizedError('Invalid basic auth credentials');
            }
            throw new \RuntimeException('Invalid basic auth credentials');
        }
        
        return new HTTPAuthorizationCredentials($scheme, $decoded);
    }
    
    public function decodeCredentials(string $credentials): array {
        $decoded = base64_decode($credentials, true);
        if ($decoded === false) {
            return ['', ''];
        }
        
        $parts = explode(':', $decoded, 2);
        if (count($parts) !== 2) {
            return [$decoded, ''];
        }
        
        return [$parts[0], $parts[1]];
    }
    
    public function encodeCredentials(string $username, string $password): string {
        return base64_encode($username . ':' . $password);
    }
    
    public function getScheme(): string {
        return $this->scheme;
    }
    
    public function getRealm(): string {
        return $this->realm;
    }
    
    public function setAutoError(bool $autoError): void {
        $this->autoError = $autoError;
    }
    
    private function raiseUnauthorizedError(string $detail): void {
        $wwwAuthenticate = 'Basic';
        if ($this->realm) {
            $wwwAuthenticate .= ' realm="' . $this->realm . '"';
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
}