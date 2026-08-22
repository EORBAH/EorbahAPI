<?php

namespace Eorbahapi\Security\Http;

use Eorbahapi\Exceptions\HTTPException;
use function Eorbahapi\Responses\JSONResponse;

class HTTPBearer {
    private bool $autoError;
    private string $scheme;
    private ?string $bearerFormat;
    private string $description;
    
    public function __construct(
        bool $autoError = true,
        string $scheme = "Bearer",
        ?string $bearerFormat = null,
        string $description = ""
    ) {
        $this->autoError = $autoError;
        $this->scheme = $scheme;
        $this->bearerFormat = $bearerFormat;
        $this->description = $description;
    }
    
    public function __invoke(): HTTPAuthorizationCredentials {
        $headers = getallheaders();
        $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);
        
        if (!isset($normalizedHeaders['authorization'])) {
            if ($this->autoError) {
                $this->raiseUnauthorizedError('Authorization header missing');
            }
            throw new HTTPException(403, 'Authorization header not found');
        }
        
        $authHeader = $normalizedHeaders['authorization'];
        
        if (!is_string($authHeader) || empty($authHeader)) {
            if ($this->autoError) {
                $this->raiseUnauthorizedError('Invalid authorization header');
            }
            throw new HTTPException(403, 'Invalid authorization header');
        }
        
        // Parse "Bearer token" ou "Basic credentials"
        $parts = explode(' ', $authHeader, 2);
        
        if (count($parts) !== 2) {
            if ($this->autoError) {
                $this->raiseUnauthorizedError('Invalid authorization format');
            }
            throw new HTTPException(403, 'Invalid authorization format');
        }
        
        $scheme = $parts[0];
        $credentials = $parts[1];
        
        // Optionnel: vérifier le schéma attendu
        if (strtolower($scheme) !== strtolower($this->scheme)) {
            if ($this->autoError) {
                $this->raiseUnauthorizedError('Invalid authorization scheme');
            }
            throw new HTTPException(403, 'Invalid authorization scheme');
        }
        
        return new HTTPAuthorizationCredentials($scheme, $credentials);
    }
    
    public function getScheme(): string {
        return $this->scheme;
    }
    
    public function getBearerFormat(): ?string {
        return $this->bearerFormat;
    }
    
    public function getDescription(): string {
        return $this->description;
    }
    
    public function setAutoError(bool $autoError): void {
        $this->autoError = $autoError;
    }
    
    private function raiseUnauthorizedError(string $detail): void {
        $wwwAuthenticate = $this->scheme;
        if ($this->bearerFormat) {
            $wwwAuthenticate .= ' format="' . $this->bearerFormat . '"';
        }
        if ($this->description) {
            $wwwAuthenticate .= ' description="' . $this->description . '"';
        }
        
        echo JSONResponse([
            'detail' => $detail,
            'error' => 'Unauthorized'
        ], 401, headers: ['WWW-Authenticate' => $wwwAuthenticate]);
        
        exit;
    }
}