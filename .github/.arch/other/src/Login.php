<?php

namespace Eorbah545\App\api;


# include with IncludeRoute
class Login {
    public $config;

    public function __construct() {
        $this->config = [
            'method' => 'GET',
            'route' => '/login'
        ];
    }

    public function __invoke($req, $res) {
        $res->JSONResponse(['status' => 'ok']);
    }
}

class RouteAPI {
    public function __invoke($router) {
        $router->get('/health', [$this, 'health']);
    }

    public function health($req, $res) {
        $res->JSONResponse(['status'=> 'ok']);
    }
}