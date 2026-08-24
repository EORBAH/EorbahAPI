<?php

namespace Eorbahapi\Responses;

use Eorbahapi\Response;

class StreamingResponse extends Response
{
    public function __construct(
        private \Generator $generator,
        int $status = 200,
        array $headers = [],
    ) {
        parent::__construct(
            body: null,
            status: $status,
            headers: $headers,
        );
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        foreach ($this->generator as $chunk) {
            echo $chunk;
            if(ob_get_level()) ob_flush();
            flush();
        }
    }
}