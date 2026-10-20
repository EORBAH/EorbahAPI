<?php

namespace Eorbahapi\Responses;

use Eorbahapi\Response;

class HTMLResponse extends Response
{
    public function __construct(
        string $content,
        int $status = 200,
        array $headers = [],
        bool $setContentType = true
    ) {
        if ($setContentType) {
            $headers['Content-Type'] ??= 'text/html; charset=UTF-8';
        }
        parent::__construct(
            body: $content,
            status: $status,
            headers: $headers,
        );
    }
}