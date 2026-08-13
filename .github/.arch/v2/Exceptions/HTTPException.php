<?php

namespace Eorbahapi\Exceptions;

class HTTPException extends \Exception {
    private int $statusCode;
    private array $headers;

    public function __construct(int $statusCode, string $message = "", array $headers = []) {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public function getStatusCode(): int { return $this->statusCode; }
    public function getHeaders(): array { return $this->headers; }
}