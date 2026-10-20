<?php

namespace Eorbahapi\Params;

/**
 * Source (emplacement) d'un paramètre de route.
 *
 * Équivalent de l'attribut `in` d'OpenAPI et de la notion d'emplacement des
 * paramètres de FastAPI : d'où vient la valeur, et donc comment la lire.
 */
enum ParamTypes: string
{
    case PATH = 'path';
    case QUERY = 'query';
    case HEADER = 'header';
    case COOKIE = 'cookie';
    case BODY = 'body';
    case FORM = 'form';
    case FILE = 'file';
    case SECURITY = 'security';

    /**
     * Emplacement OpenAPI (`in`) correspondant, ou null lorsque la source
     * n'apparaît pas comme paramètre dans la spécification (corps, sécurité).
     */
    public function in(): ?string
    {
        return match ($this) {
            self::PATH, self::QUERY, self::HEADER, self::COOKIE => $this->value,
            default => null,
        };
    }

    /**
     * Vrai lorsque la valeur arrive sous forme de chaîne (ou de tableau de
     * chaînes) : la coercition laxiste de Svinx\Validator est alors requise.
     */
    public function isTextual(): bool
    {
        return match ($this) {
            self::PATH, self::QUERY, self::HEADER, self::COOKIE, self::FORM, self::FILE => true,
            default => false,
        };
    }
}
