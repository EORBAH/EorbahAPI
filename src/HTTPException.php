<?php

namespace EorBah545\Eorbahapi;

class HTTPException extends \Exception {
    public function __construct($message = "", $code = 0, \Throwable $previous) {
        parent::__construct($message, $code, $previous);
    }
}