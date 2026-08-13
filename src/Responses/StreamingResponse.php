<?php

namespace Eorbahapi\Responses;

function StreamingResponse(iterable $generator, string $contentType = 'text/event-stream'): iterable
{
    if (!headers_sent()) {
        header('Content-Type: ' . $contentType);
        header('Cache-Control: no-cache');
    }

    foreach ($generator as $event) {
        echo $event;
        ob_flush();
        flush();
        yield $event;
    }
}
