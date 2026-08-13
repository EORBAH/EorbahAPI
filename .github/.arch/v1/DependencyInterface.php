<?php


namespace EorBah545\Eorbahapi;

interface DependencyInterface {
    public function resolve(Request $request, Response $response): mixed;
}