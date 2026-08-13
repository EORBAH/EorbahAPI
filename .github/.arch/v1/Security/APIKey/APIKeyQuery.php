<?php

namespace EorBah545\Eorbahapi\Security\APIKey;

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
            throw new \RuntimeException('API key query parameter not found');
        }
        
        $apiKey = $_GET[$this->queryParamName];
        
        if (empty($apiKey) || !is_string($apiKey)) {
            if ($this->autoError) {
                $this->raiseForbiddenError('Invalid API key format');
            }
            throw new \RuntimeException('Invalid API key format');
        }
        
        return $apiKey;
    }
    
    public function validate(string $validKey): string {
        $apiKey = $this->__invoke();
        
        if ($apiKey !== $validKey) {
            if ($this->autoError) {
                $this->raiseForbiddenError('Invalid API key');
            }
            throw new \RuntimeException('Invalid API key');
        }
        
        return $apiKey;
    }
    
    public function validateWithCallback(callable $validationCallback): string {
        $apiKey = $this->__invoke();
        
        if (!$validationCallback($apiKey)) {
            if ($this->autoError) {
                $this->raiseForbiddenError('API key validation failed');
            }
            throw new \RuntimeException('API key validation failed');
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
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/json');
        
        echo json_encode([
            'detail' => $detail,
            'error' => 'Forbidden'
        ]);
        
        exit;
    }
}