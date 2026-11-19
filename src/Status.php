<?php

namespace Eorbahapi;

/**
 * Constantes et utilitaires des statuts HTTP.
 *
 *     Status::HTTP_404_NOT_FOUND;      // 404
 *     Status::text(404);               // 'Not Found'
 *     Status::isSuccess(201);          // true
 */
class Status
{
    public const HTTP_100_CONTINUE = 100;
    public const HTTP_101_SWITCHING_PROTOCOLS = 101;
    public const HTTP_200_OK = 200;
    public const HTTP_201_CREATED = 201;
    public const HTTP_202_ACCEPTED = 202;
    public const HTTP_203_NON_AUTHORITATIVE_INFORMATION = 203;
    public const HTTP_204_NO_CONTENT = 204;
    public const HTTP_205_RESET_CONTENT = 205;
    public const HTTP_206_PARTIAL_CONTENT = 206;
    public const HTTP_300_MULTIPLE_CHOICES = 300;
    public const HTTP_301_MOVED_PERMANENTLY = 301;
    public const HTTP_302_FOUND = 302;
    public const HTTP_303_SEE_OTHER = 303;
    public const HTTP_304_NOT_MODIFIED = 304;
    public const HTTP_307_TEMPORARY_REDIRECT = 307;
    public const HTTP_308_PERMANENT_REDIRECT = 308;
    public const HTTP_400_BAD_REQUEST = 400;
    public const HTTP_401_UNAUTHORIZED = 401;
    public const HTTP_402_PAYMENT_REQUIRED = 402;
    public const HTTP_403_FORBIDDEN = 403;
    public const HTTP_404_NOT_FOUND = 404;
    public const HTTP_405_METHOD_NOT_ALLOWED = 405;
    public const HTTP_406_NOT_ACCEPTABLE = 406;
    public const HTTP_408_REQUEST_TIMEOUT = 408;
    public const HTTP_409_CONFLICT = 409;
    public const HTTP_410_GONE = 410;
    public const HTTP_411_LENGTH_REQUIRED = 411;
    public const HTTP_412_PRECONDITION_FAILED = 412;
    public const HTTP_413_REQUEST_ENTITY_TOO_LARGE = 413;
    public const HTTP_415_UNSUPPORTED_MEDIA_TYPE = 415;
    public const HTTP_418_IM_A_TEAPOT = 418;
    public const HTTP_422_UNPROCESSABLE_ENTITY = 422;
    public const HTTP_425_TOO_EARLY = 425;
    public const HTTP_426_UPGRADE_REQUIRED = 426;
    public const HTTP_428_PRECONDITION_REQUIRED = 428;
    public const HTTP_429_TOO_MANY_REQUESTS = 429;
    public const HTTP_431_REQUEST_HEADER_FIELDS_TOO_LARGE = 431;
    public const HTTP_451_UNAVAILABLE_FOR_LEGAL_REASONS = 451;
    public const HTTP_500_INTERNAL_SERVER_ERROR = 500;
    public const HTTP_501_NOT_IMPLEMENTED = 501;
    public const HTTP_502_BAD_GATEWAY = 502;
    public const HTTP_503_SERVICE_UNAVAILABLE = 503;
    public const HTTP_504_GATEWAY_TIMEOUT = 504;
    public const HTTP_505_HTTP_VERSION_NOT_SUPPORTED = 505;
    public const HTTP_511_NETWORK_AUTHENTICATION_REQUIRED = 511;

    /** @var array<int, string> */
    private const REASONS = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        415 => 'Unsupported Media Type',
        418 => "I'm a Teapot",
        422 => 'Unprocessable Entity',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        511 => 'Network Authentication Required',
    ];

    /** Libellé d'un statut, ou `$default` s'il est inconnu. */
    public static function text(int $code, string $default = 'Unknown Status Code'): string
    {
        return self::REASONS[$code] ?? $default;
    }

    /** Alias de `text()` pour les habitués de Starlette. */
    public static function reason(int $code, string $default = 'Unknown Status Code'): string
    {
        return self::text($code, $default);
    }

    public static function isSuccess(int $code): bool
    {
        return $code >= 200 && $code < 300;
    }

    public static function isRedirect(int $code): bool
    {
        return $code >= 300 && $code < 400;
    }

    public static function isClientError(int $code): bool
    {
        return $code >= 400 && $code < 500;
    }

    public static function isServerError(int $code): bool
    {
        return $code >= 500;
    }

    public static function isError(int $code): bool
    {
        return $code >= 400;
    }
}
