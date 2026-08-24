<?php

namespace Eorbahapi\Security\APIKey;

use Eorbahapi\Exceptions\HTTPException;
use Eorbahapi\Responses\JSONResponse;

class APIKeyQuery {
    private string $queryParamName;
    private bool $autoError;
    
    public function __construct(string $queryParamName = 'api_key', bool $autoError = true) {
        $this->queryParamName = $queryParamName;
        $this->autoError = $autoError;
    }
    
    public function __invoke(): string {
        if (!isset($_GET[$this->queryParamName])) {
            if ($this->autoError) {
                $this->raiseForbiddenError('API key query parameter missing');
            }
            throw new HTTPException(403, 'API key query parameter not found');
        }
        
        $apiKey = $_GET[$this->queryParamName];
        
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
    
    public function getName(): string {
        return $this->queryParamName;
    }
    
    public function setAutoError(bool $autoError): void {
        $this->autoError = $autoError;
    }
    
    private function raiseForbiddenError(string $detail): void {
        $res = new  JSONResponse([
            'detail' => $detail,
            'error' => 'Forbidden'
        ], 403);
        $res->send();
        exit;
    }
}