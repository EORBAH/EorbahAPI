<?php

namespace Eorbahapi\Security\HTTP;

use Eorbahapi\Exceptions\HTTPException;
use Eorbahapi\Request;

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
    
    public function __invoke(?Request $request = null): HTTPAuthorizationCredentials {
        $request ??= new Request();
        $authHeader = $request->getHeader('Authorization');

        if ($authHeader === null || $authHeader === '') {
            if ($this->autoError) {
                $this->raiseUnauthorizedError('Authorization header missing');
            }
            throw new HTTPException(401, 'Authorization header not found');
        }
        
        if (!is_string($authHeader) || empty($authHeader)) {
            if ($this->autoError) {
                $this->raiseUnauthorizedError('Invalid authorization header');
            }
            throw new HTTPException(401, 'Invalid authorization header');
        }
        
        // Parse "Basic base64(username:password)"
        $parts = explode(' ', $authHeader, 2);
        
        if (count($parts) !== 2) {
            if ($this->autoError) {
                $this->raiseUnauthorizedError('Invalid authorization format');
            }
            throw new HTTPException(401, 'Invalid authorization format');
        }
        
        $scheme = $parts[0];
        $credentials = $parts[1];
        
        // Vérifier que c'est bien du Basic
        if (strtolower($scheme) !== 'basic') {
            if ($this->autoError) {
                $this->raiseUnauthorizedError('Invalid authorization scheme');
            }
            throw new HTTPException(401, 'Invalid authorization scheme');
        }
        
        // Décoder les credentials base64
        $decoded = base64_decode($credentials, true);
        if ($decoded === false) {
            if ($this->autoError) {
                $this->raiseUnauthorizedError('Invalid basic auth credentials');
            }
            throw new HTTPException(401, 'Invalid basic auth credentials');
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
    
    /**
     * Valeur de l'en-tête `WWW-Authenticate` associé au schéma.
     */
    public function challenge(): string {
        $wwwAuthenticate = 'Basic';
        if ($this->realm) {
            $wwwAuthenticate .= ' realm="' . $this->realm . '"';
        }

        return $wwwAuthenticate;
    }

    private function raiseUnauthorizedError(string $detail): never {
        throw new HTTPException(401, $detail, ['WWW-Authenticate' => $this->challenge()]);
    }
}