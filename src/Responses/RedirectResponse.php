<?php

namespace Eorbahapi\Responses;

use Eorbahapi\Response;

class RedirectResponse extends Response
{
    public function __construct(
        string $url, int $status = 302
    ) {
        $headers['Location'] = $url;
        parent::__construct(
            body: 'redirect:' . $status . ':' . $url,
            status: $status,
            headers: $headers
        );
    }
}