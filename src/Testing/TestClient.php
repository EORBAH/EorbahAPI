<?php

namespace Eorbahapi\Testing;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Request;
use Eorbahapi\Response;

/**
 * Client de test HTTP (sans serveur ni socket).
 *
 * Options acceptées par `request()` :
 *
 *  - `headers`  : en-têtes de la requête (noms insensibles à la casse) ;
 *  - `query`    : paramètres de requête (complète ceux de l'URI) ;
 *  - `json`     : corps encodé en JSON (Content-Type: application/json) ;
 *  - `form`     : corps de formulaire ($_POST) ;
 *  - `body`     : corps brut (chaîne) ;
 *  - `cookies`  : cookies ($_COOKIE) ;
 *  - `files`    : fichiers ($_FILES) ;
 *  - `server`   : surcharges de $_SERVER.
 *
 *     $client = new TestClient($app);
 *     $client->get('/users?page=2')->assertStatus(200);
 *     $client->post('/users', ['json' => ['name' => 'Jean']])->json();
 */
class TestClient implements TestClientInterface
{
    public function __construct(private readonly EorbahAPI $app)
    {
    }

    /**
     * Exécute une requête et retourne sa réponse (aucun envoi).
     */
    public function request(
        string $method,
        string $uri,
        array $options = []
    ): TestResponse {
        $parsedQuery = [];
        $queryString = parse_url($uri, PHP_URL_QUERY);

        if (is_string($queryString) && $queryString !== '') {
            parse_str($queryString, $parsedQuery);
        }

        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        $headers = $options['headers'] ?? [];
        $body = $options['body'] ?? null;

        if (array_key_exists('json', $options)) {
            $body = $options['json'];
            $headers = $this->withDefaultHeader($headers, 'Content-Type', 'application/json');
        } elseif (array_key_exists('form', $options)) {
            $body = null;
            $headers = $this->withDefaultHeader($headers, 'Content-Type', 'application/x-www-form-urlencoded');
        }

        $server = array_merge([
            'REQUEST_METHOD' => strtoupper($method),
            'REQUEST_URI' => $path,
            'QUERY_STRING' => (string) parse_url($uri, PHP_URL_QUERY),
            'SCRIPT_NAME' => '',
            'REMOTE_ADDR' => '127.0.0.1',
        ], $options['server'] ?? []);

        $request = new Request(
            body: $body,
            server: $server,
            query: array_merge($parsedQuery, $options['query'] ?? []),
            postData: $options['form'] ?? [],
            cookies: $options['cookies'] ?? [],
            files: $options['files'] ?? [],
            headers: $headers,
        );

        // Une réponse neuve par requête : l'application ne doit pas hériter
        // d'un statut ou d'un en-tête de la requête précédente.
        $response = $this->app->handle($request, new Response());

        return new TestResponse($response);
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

    private function withDefaultHeader(array $headers, string $name, string $value): array
    {
        foreach (array_keys($headers) as $key) {
            if (strcasecmp((string) $key, $name) === 0) {
                return $headers;
            }
        }

        $headers[$name] = $value;

        return $headers;
    }
}
