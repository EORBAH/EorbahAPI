<?php

namespace Eorbahapi\Examples;

class UserRouter {
    public function __register_routes($router) {
        $router->get('/login', [$this, 'login']);
    }

    public function login() {
        return [
            'status' => 'ok',
            'version' => '1.0.0',
            'timestamp' => '2026-07-08T12:00:00Z',
            'uptime' => 123456.78
        ];
    }
}