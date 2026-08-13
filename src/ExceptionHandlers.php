<?php

namespace Eorbahapi;

use Eorbahapi\Exceptions\HTTPException;
use Eorbahapi\Exceptions\ValidationException;
use function Eorbahapi\Responses\JSONResponse;

class ExceptionHandlers
{
    private $dev;
    public function __construct(bool $dev = false) {
        $this->dev = $dev;
    }
    /**
     * Gère les HTTPException (404, 403, 500, etc.)
     */
    public function httpExceptionHandler(HTTPException $e, Request $request, Response $response): Response
    {
        $statusCode = $e->getStatusCode();
        $response->status($statusCode);

        // Ajout des en-têtes personnalisés éventuels
        foreach ($e->getHeaders() as $name => $value) {
            $response->header($name, $value);
        }

        // Réponse structurée en JSON (ou autre selon Accept header)
        echo JSONResponse([
            'error' => true,
            'status' => $statusCode,
            'message' => $e->getMessage() ?: $this->getDefaultMessage($statusCode)
        ], $statusCode);

        return $response;
    }

    /**
     * Gère les erreurs de validation des requêtes
     */
    public function requestValidationExceptionHandler(ValidationException $e, Request $request, Response $response): Response
    {
        $response->status(422);
        echo JSONResponse([
            'error' => true,
            'status' => 422,
            'message' => 'Validation error',
            'details' => $e->getErrors()
        ], 422);
        return $response;
    }

    /**
     * Gestionnaire de fallback pour toute autre exception
     */
    public function genericExceptionHandler(\Throwable $e, Request $request, Response $response): Response
    {
        $response->status(500);
        $isDebug = $this->dev || ($_ENV['APP_DEBUG'] ?? null) === '1' || ($_ENV['APP_DEBUG'] ?? null) === 'true' || ($_ENV['APP_ENV'] ?? null) === 'dev' || ($_ENV['APP_ENV'] ?? null) === 'development';

        echo JSONResponse([
            'error' => true,
            'status' => 500,
            'message' => 'Internal Server Error',
            'debug' => $isDebug ? $e->getMessage() : null
        ], 500);
        return $response;
    }

    /**
     * Enregistre tous les gestionnaires dans l'application
     */
    public function overrideExceptionHandlers(EorbahAPI $app): void
    {
        // On stocke dans l'application un tableau de callbacks par type d'exception
        $app->setExceptionHandler(HTTPException::class, [$this, 'httpExceptionHandler']);
        $app->setExceptionHandler(ValidationException::class, [$this, 'requestValidationExceptionHandler']);
        $app->setExceptionHandler(\Throwable::class, [$this, 'genericExceptionHandler']);
    }

    private function getDefaultMessage(int $statusCode): string
    {
        $messages = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
        ];
        return $messages[$statusCode] ?? 'Error';
    }
}