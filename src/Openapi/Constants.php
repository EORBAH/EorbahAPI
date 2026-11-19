<?php

namespace Eorbahapi\Openapi;

/**
 * Constantes partagées par le générateur OpenAPI.
 */
final class Constants
{
    public const METHODS_WITH_BODY = ['GET', 'HEAD', 'POST', 'PUT', 'DELETE', 'PATCH'];

    /** Préfixe des références vers `components.schemas`. */
    public const REF_PREFIX = '#/components/schemas/';

    /** Gabarit de référence d'un modèle. */
    public const REF_TEMPLATE = '#/components/schemas/{model}';

    /**
     * Version de la spécification produite.
     *
     * EorbahAPI repose sur les schémas de `svinx/validator`, qui expriment les
     * bornes exclusives (`exclusiveMinimum`, `exclusiveMaximum`) sous forme
     * numérique : c'est la sémantique de JSON Schema 2020-12, adoptée par
     * OpenAPI 3.1.
     */
    public const OPENAPI_VERSION = '3.1.0';

    public const DEFAULT_TITLE = 'EorbahAPI';

    public const DEFAULT_VERSION = '0.1.0';

    /** Nom du composant décrivant une erreur de validation (HTTP 422). */
    public const VALIDATION_ERROR_SCHEMA = 'HTTPValidationError';

    /** Schéma générique des réponses réussies, quand le type n'est pas connu. */
    public const EMPTY_SCHEMA = [];
}
