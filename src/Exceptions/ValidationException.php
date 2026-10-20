<?php

namespace Eorbahapi\Exceptions;

/**
 * Erreur de validation d'une requête (ou d'une réponse).
 *
 * Les erreurs suivent la forme de celles de Svinx\Validator / FastAPI :
 *
 *     [
 *         ['loc' => ['query', 'limit'], 'type' => 'value_error', 'msg' => '...'],
 *         ['loc' => ['body', 'email'],  'type' => 'missing',     'msg' => '...'],
 *     ]
 *
 * elles sont exposées telles quelles dans la réponse HTTP 422 produite par
 * `ExceptionHandlers::requestValidationExceptionHandler()`.
 */
class ValidationException extends \Exception {
    private array $errors;
    private array $headers;

    public function __construct(
        array $errors = [],
        string $message = 'Validation error',
        array $headers = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->errors = $errors;
        $this->headers = $headers;
    }

    public function getErrors(): array { return $this->errors; }

    public function getHeaders(): array { return $this->headers; }
}
