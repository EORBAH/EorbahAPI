<?php

namespace EorBah545\Eorbahapi\Security\APIKey;

class APIKeyHeader {
    private string $headerName;
    private bool $autoError;
    
    public function __construct(string $headerName = 'X-API-Key', bool $autoError = true) {
        $this->headerName = $headerName;
        $this->autoError = $autoError;
    }
    
    public function __invoke(): string {
        // getallheaders() est disponible dans PHP >= 5.4
        $headers = getallheaders();
        
        // Normalisation du nom du header (insensible à la casse)
        $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);
        $normalizedHeaderName = strtolower($this->headerName);
        
        if (!isset($normalizedHeaders[$normalizedHeaderName])) {
            if ($this->autoError) {
                $this->raiseForbiddenError('API key header missing');
            }
            throw new \RuntimeException('API key header not found');
        }
        
        $apiKey = $normalizedHeaders[$normalizedHeaderName];
        
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
        return $this->headerName;
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