<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\APIRouter;

class UserRouter {
    public $router;

    public function __construct() {
        $this->router = new APIRouter(tags: ['UserInfo']);
        $this->__register_routes();
    }

    public function __register_routes() {
        $this->router->get('/login', [$this, 'login']);
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

$app = new EorbahAPI(dev: true);

$auth = new APIRouter();

$auth->get('/login', function () {
    return [
        'status' => 'ok',
        'version' => '1.0.0',
        'timestamp' => '2026-07-08T12:00:00Z',
        'uptime' => 123456.78
    ];
});

$app->include_router(router_class: UserRouter::class, prefix: '/users');
$app->include_router(router: $auth);
$app->register_routes(UserRouteur::class);
$app->run();