<?php

namespace Eorbahapi\Security\APIKey;

use Eorbahapi\Exceptions\HTTPException;
use Eorbahapi\Request;

class APIKeyHeader {
    private string $headerName;
    private bool $autoError;
    
    public function __construct(string $headerName = 'X-API-Key', bool $autoError = true) {
        $this->headerName = $headerName;
        $this->autoError = $autoError;
    }
    
    public function __invoke(?Request $request = null): string {
        $request ??= new Request();

        $apiKey = $request->getHeader($this->headerName);

        if ($apiKey === null) {
            if ($this->autoError) {
                $this->raiseForbiddenError('API key header missing');
            }
            throw new HTTPException(403, 'API key header not found');
        }
        
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
        return $this->headerName;
    }
    
    public function setAutoError(bool $autoError): void {
        $this->autoError = $autoError;
    }
    
    private function raiseForbiddenError(string $detail): never {
        throw new HTTPException(403, $detail);
    }
}