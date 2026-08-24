<?php


namespace Eorbahapi\Dependency;

interface DependencyInterface {
    public function resolve(Request $request, Response $response): mixed;
}