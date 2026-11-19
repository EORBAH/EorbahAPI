<?php

namespace Eorbahapi\Contracts;

use Eorbahapi\Testing\TestResponse;

/**
 * Contrat du client de test HTTP.
 *
 * `Eorbahapi\Testing\TestClientInterface` en hérite : le client actuel satisfait
 * donc déjà ce contrat figé.
 */
interface TestClientInterface
{
    public function request(string $method, string $uri, array $options = []): TestResponse;

    public function get(string $uri, array $options = []): TestResponse;

    public function post(string $uri, array $options = []): TestResponse;

    public function put(string $uri, array $options = []): TestResponse;

    public function patch(string $uri, array $options = []): TestResponse;

    public function delete(string $uri, array $options = []): TestResponse;
}
