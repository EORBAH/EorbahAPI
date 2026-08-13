<?php

namespace PhoenixAccount\Users;

use EorBah545\Eorbahapi\Response;
use PhoenixAccount\Users\Service;
use EorBah545\Eorbahapi\HTTPException;
use EorBah545\Eorbahapi\datastructures\UploadFile;

class Router {
    private $service;

    public function __construct() {
        $this->service = new Service();
    }

    public function __invoke($router) {
        $router->get('/users/{user_id}/{file_name}', [$this, 'users']);
    }

    public function users(Response $res, $user_id, $file_name) {
        $file = $this->service->get_users_files($user_id, $file_name);
        if (!$file) {
            $res->status(404)->send('File not found');
        }
    }
}