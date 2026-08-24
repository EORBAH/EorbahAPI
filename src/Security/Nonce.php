<?php

namespace Eorbahapi\Security;

class Nonce {

    public function __construct(
        public ?string $nonce = null
    ) {
        $this->nonce = $this->setNonceClient($nonce ?? bin2hex(random_bytes(32))
    }

    private function setNonceClient(string $nonce)
    {
        header("Content-Security-Policy: script-src 'self' 'nonce-$nonce'; font-src 'self'; object-src 'none';");
        return $nonce;
    }
}