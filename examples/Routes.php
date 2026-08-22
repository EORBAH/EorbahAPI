<?php

namespace Eorbahapi\Examples;

class UserRouteur {
    public function __register_routes($router) {
        $router->post('/me', [$this, 'me']);
        $router->post('/health', [$this, 'health']);
    }

    public function health() {
        return [
            'status'    => 'ok',
            'version'   => '1.0.0',
            'timestamp' => '2026-07-08T12:00:00Z',
            'uptime'    => 123456.78
        ];
    }

    public function me() {
        return ['status' => 'ok'];
    }
}