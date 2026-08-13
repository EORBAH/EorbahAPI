<?php

namespace PhoenixAccount\Auth;

class Service {
    private $jwt;
    public function __construct() {
        $this->jwt = new JWT($_ENV['SECRET_KEY'], $_ENV['ALGORITHM']);
    }

    public function isJwtTokenExprired($token) {
        try {
            $isExpired = $this->jwt->isExpired($token);
            return ($isExpired ? true : false);
        } catch (\Exception $error) {
            return false;
        }
    }
}