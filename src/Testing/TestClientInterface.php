<?php

namespace Eorbahapi\Testing;

use Eorbahapi\EorbahAPI;

interface TestClientInterface {
    public function __construct(EorbahAPI $app) {}

    public function get(string $uri, array $options = []): TestResponse;
    public function post(string $uri, array $options = []): TestResponse;
    public function put(string $uri, array $options = []): TestResponse;
    public function patch(string $uri, array $options = []): TestResponse;
    public function delete(string $uri, array $options = []): TestResponse;
    public function request(
        string $method,
        string $uri, 
        array $options = []
    ): TestResponse;

}