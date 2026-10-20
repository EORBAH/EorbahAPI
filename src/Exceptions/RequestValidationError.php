<?php

namespace Eorbahapi\Exceptions;

use Svinx\Validator\ValidationError;

/**
 * Erreur de validation d'un paramètre de requête (HTTP 422).
 *
 * Enveloppe une erreur de Svinx\Validator en y ajoutant la localisation
 * (`loc`) du paramètre fautif, au format utilisé par FastAPI :
 *
 *     ['loc' => ['query', 'limit'], 'type' => 'value_error', 'msg' => 'Le paramètre query 'limit' doit être >= 1']
 */
class RequestValidationError extends ValidationException
{
    /**
     * @param string $location Emplacement (`query`, `header`, `path`, `body`...).
     * @param string $name     Nom du paramètre fautif.
     */
    public static function at(
        string $location,
        string $name,
        string $message,
        string $type = 'value_error',
        ?\Throwable $previous = null
    ): static {
        return new static(
            [[
                'loc' => [$location, $name],
                'type' => $type,
                'msg' => $message,
            ]],
            'Request validation error',
            [],
            $previous
        );
    }

    /**
     * Convertit une erreur de Svinx\Validator en erreur de requête localisée.
     */
    public static function fromValidationError(string $location, string $name, ValidationError $error): static
    {
        return static::at($location, $name, $error->getMessage(), 'value_error', $error);
    }
}
