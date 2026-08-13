<?php


namespace Eorbahapi;

interface DependencyInterface {
    public function resolve(Request $request, Response $response): mixed;
}