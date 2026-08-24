<?php

namespace Eorbahapi\Testing;

use Eorbahapi\EorbahAPI;

class TestClient implements TestClientInterface
{
    public function __construct(private readonly EorbahAPI $app)
    {
    }

    public function request(
        string $method,
        string $uri,
        array $options = []
    ): TestResponse {
        /* $request = $this->createRequest(
             $method,
             $uri,
             $options
         );

         $response = $this->app->handle($request);*/

        return new TestResponse("");
    }

    public function get(
        string $uri,
        array $options = []
    ): TestResponse {
        return $this->request('GET', $uri, $options);
    }

    public function post(
        string $uri,
        array $options = []
    ): TestResponse {
        return $this->request('POST', $uri, $options);
    }

    public function put(
        string $uri,
        array $options = []
    ): TestResponse {
        return $this->request('PUT', $uri, $options);
    }

    public function patch(
        string $uri,
        array $options = []
    ): TestResponse {
        return $this->request('PATCH', $uri, $options);
    }

    public function delete(
        string $uri,
        array $options = []
    ): TestResponse {
        return $this->request('DELETE', $uri, $options);
    }
}