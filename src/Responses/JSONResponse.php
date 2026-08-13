<?php

namespace Eorbahapi\Responses;

use function json_encode;

function JSONResponse($content, int $statusCode = 200, array $headers = [], bool $setContentType = true): string
{
    if (!headers_sent()) {
        if ($statusCode !== 200 || !empty($headers)) {
            http_response_code($statusCode);
        }

        if ($setContentType) {
            header('Content-Type: application/json; charset=UTF-8');
        }

        foreach ($headers as $name => $value) {
            header($name . ': ' . $value, false);
        }
    }

    $json = json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return $json;
}