<?php

namespace Eorbahapi\Testing;

/**
 * Client de test HTTP.
 *
 * Il construit une `Eorbahapi\Request` complète à partir des options (URI,
 * en-têtes, corps, query, cookies, fichiers) et la confie à
 * `EorbahAPI::handle()` : rien n'est envoyé, la réponse est retournée.
 *
 *     $client = new TestClient(new EorbahAPI());
 *
 *     $response = $client->post('/users', ['json' => ['name' => 'Jean']]);
 *     $response->status();   // 201
 *     $response->json();     // ['id' => 1, 'name' => 'Jean']
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
