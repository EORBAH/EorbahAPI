<?php

namespace Eorbahapi\Responses;

function RedirectResponse(string $url, int $statusCode = 302): string
{
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Location: ' . $url);
    }

    return 'redirect:' . $statusCode . ':' . $url;
}
