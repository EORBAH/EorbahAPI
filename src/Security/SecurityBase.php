<?php

namespace Eorbahapi\Security;

class SecurityBase {
    public function __construct(
        public $model,
        public string $scheme_name
    ) {}
}