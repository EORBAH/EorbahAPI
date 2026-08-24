<?php

namespace Eorbahapi\Responses;

use Eorbahapi\Response;

class JSONResponse extends Response
{
    public function __construct(
        mixed $data,
        int $status = 200,
        array $headers = [],
    ) {
        $headers['Content-Type'] ??= 'application/json';

        parent::__construct(
            body: json_encode($data),
            status: $status,
            headers: $headers,
        );
    }
}