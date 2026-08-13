<?php

namespace EorBah545\Eorbahapi\Exceptions;

class ValidationException extends \Exception {
    private array $errors;

    public function __construct(array $errors) {
        parent::__construct("Validation error");
        $this->errors = $errors;
    }

    public function getErrors(): array { return $this->errors; }
}