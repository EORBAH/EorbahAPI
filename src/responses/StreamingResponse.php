<?php

namespace EorBah545\Eorbahapi\responses;

class StreamingResponse
{
    public function __construct()
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        @ob_end_flush();
        @ob_implicit_flush(true);
    }

    public function push()
    {
        flush();
    }

    public function send(...$message)
    {
        echo "data: " . implode('', $message) . "\n\n";
    }   
}