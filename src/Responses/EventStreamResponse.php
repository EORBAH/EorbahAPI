<?php

namespace Eorbahapi\Responses;

class EventStreamResponse extends StreamingResponse
{
    public function __construct(
        \Generator $generator,
        int $status = 200,
        array $headers = [],
    ) {
        $headers['Content-Type'] ??= 'text/event-stream';
        $headers['Cache-Control'] ??= 'no-cache';
        $headers['Connection'] ??= 'keep-alive';

        parent::__construct(
            generator: $generator,
            status: $status,
            headers: $headers,
        );
    }
}