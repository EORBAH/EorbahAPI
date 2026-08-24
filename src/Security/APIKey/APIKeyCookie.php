<?php

namespace Eorbahapi\Security\APIKey;

use Eorbahapi\Response;
use Eorbahapi\Exceptions\HTTPException;
use Eorbahapi\Responses\JSONResponse;

class APIKeyCookie extends Response {
    private string $cookieName;
    private bool $autoError;
    
    public function __construct(string $cookieName = 'api_key', bool $autoError = true) {
        $this->cookieName = $cookieName;
        $this->autoError = $autoError;
    }
    
    public function __invoke(): string {
        if (!isset($_COOKIE[$this->cookieName])) {
            if ($this->autoError) {
                $this->raiseForbiddenError('Cookie API key missing');
            }
            throw new HTTPException(403, 'API key cookie not found');
        }
        
        $apiKey = $_COOKIE[$this->cookieName];
        
        if (empty($apiKey) || !is_string($apiKey)) {
            if ($this->autoError) {
                $this->raiseForbiddenError('Invalid API key format');
            }
            throw new HTTPException(403, 'Invalid API key format');
        }
        
        return $apiKey;
    }
    
    public function validate(string $validKey): string {
        $apiKey = $this->__invoke();
        
        if ($apiKey !== $validKey) {
            if ($this->autoError) {
                $this->raiseForbiddenError('Invalid API key');
            }
            throw new HTTPException(403, 'Invalid API key');
        }
        
        return $apiKey;
    }
    
    public function validateWithCallback(callable $validationCallback): string {
        $apiKey = $this->__invoke();
        
        if (!$validationCallback($apiKey)) {
            if ($this->autoError) {
                $this->raiseForbiddenError('API key validation failed');
            }
            throw new HTTPException(403, 'API key validation failed');
        }
        
        return $apiKey;
    }
    
    public function setCookie(string $apiKey, array $options = []): void {
        $this->cookie($this->cookieName, $apiKey, $options);
    }
    
    public function deleteCookie(): void {
        $this->clearCookie($this->cookieName);
    }
    
    public function getName(): string {
        return $this->cookieName;
    }
    
    public function setAutoError(bool $autoError): void {
        $this->autoError = $autoError;
    }
    
    private function raiseForbiddenError(string $detail): void {
        $res = new JSONResponse([
            'detail' => $detail,
            'error' => 'Forbidden'
        ], 403); 
        $res->send();
        exit;
    }
}