<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Exceptions\HTTPException;
use Eorbahapi\Exceptions\ValidationException;
use Eorbahapi\Responses\JSONResponse;

/**
 * Gestion des erreurs : exceptions HTTP, validation, gestionnaire personnalisé.
 */
final class ErrorsExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // HTTPException : statut et message contrôlés.
        $app->get('/forbidden', function () {
            throw new HTTPException(403, 'Accès interdit');
        });

        // ValidationException : réponse 422 avec le détail des erreurs.
        $app->post('/validate', function () {
            throw new ValidationException(
                [['loc' => ['body', 'name'], 'type' => 'missing', 'msg' => 'Le champ name est requis.']],
                'Request validation error'
            );
        });

        // Gestionnaire personnalisé pour un type d'exception.
        $app->setExceptionHandler(\DomainException::class, fn (\Throwable $error) => new JSONResponse(
            ['domain' => $error->getMessage()],
            400
        ));
        $app->get('/domain', function () {
            throw new \DomainException('règle métier violée');
        });

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/forbidden', status: 403, json: [
                'error' => true,
                'status' => 403,
                'message' => 'Accès interdit',
            ]),

            self::scenario('POST', '/validate', ['json' => []], status: 422, json: [
                'error' => true,
                'status' => 422,
                'message' => 'Request validation error',
                'details' => [['loc' => ['body', 'name'], 'type' => 'missing', 'msg' => 'Le champ name est requis.']],
            ]),

            self::scenario('GET', '/domain', status: 400, json: ['domain' => 'règle métier violée']),
        ];
    }
}
