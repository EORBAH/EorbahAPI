<?php

namespace Eorbahapi\Responses;

function HTMLResponse(string $content, int $statusCode = 200, array $headers = [], bool $setContentType = true): string
{
    if (!headers_sent()) {
        http_response_code($statusCode);

        if ($setContentType) {
            header('Content-Type: text/html; charset=UTF-8');
        }

        foreach ($headers as $name => $value) {
            header($name . ': ' . $value, false);
        }
    }

    return $content;
}
