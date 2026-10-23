<?php

namespace Eorbahapi;

/**
 * Requête HTTP.
 *
 * Toutes les sources de données sont injectables : par défaut la requête lit les
 * superglobales et `php://input`, mais un runner personnalisé (ou le TestClient)
 * peut fournir ses propres valeurs :
 *
 *     $request = new Request(
 *         server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/users'],
 *         query: ['page' => '2'],
 *         headers: ['Content-Type' => 'application/json'],
 *         body: ['name' => 'Jean'],
 *     );
 */
class Request {
    private ?array $headersCache = null;

    /**
     * @param array|string|null $body      Corps déjà décodé (tableau) ou brut (chaîne JSON).
     * @param array             $params    Paramètres de route.
     * @param array|null        $server    Surcharge de $_SERVER.
     * @param array|null        $query     Surcharge de $_GET.
     * @param array|null        $postData  Surcharge de $_POST.
     * @param array|null        $cookies   Surcharge de $_COOKIE.
     * @param array|null        $files     Surcharge de $_FILES.
     * @param array|null        $headers   En-têtes déjà extraits (noms insensibles à la casse).
     */
    public function __construct(
        private array|string|null $body = null,
        private array $params = [],
        private ?array $server = null,
        private ?array $query = null,
        private ?array $postData = null,
        private ?array $cookies = null,
        private ?array $files = null,
        private ?array $headers = null,
    ) {}

    // ------------------------------------------------------------------
    // Surcharges injectées
    // ------------------------------------------------------------------

    /**
     * Valeur de `$_SERVER` (injectée ou réelle).
     */
    public function server(?string $key = null, mixed $default = null): mixed
    {
        $server = $this->server ?? $_SERVER;

        return $key === null ? $server : ($server[$key] ?? $default);
    }

    /**
     * Copie de la requête pointant sur une autre URI (utilisé par les
     * sous-applications montées).
     */
    public function withUri(string $uri): static
    {
        $clone = clone $this;
        $clone->server = array_merge(
            $this->server ?? $_SERVER,
            ['REQUEST_URI' => $uri, 'PATH_INFO' => $uri]
        );

        return $clone;
    }

    /**
     * Copie de la requête avec un autre corps (utilisé par JSON-RPC : les
     * `params` de l'enveloppe deviennent le corps pour les attributs `#[Body]`).
     */
    public function withBody(array|string|null $body): static
    {
        $clone = clone $this;
        $clone->body = $body;
        $clone->headersCache = null;

        return $clone;
    }

    public function params(array|null|string $path = null) {
        $query = $this->query();

        if (is_array($path)) {
            $this->params = $path;
        } elseif (is_string($path)) {
            return $this->params[$path] ?? null;
        }

        foreach ($query as $queryName => $queryValue) {
            $this->params[$queryName] = $queryValue;
        }

        return $this->params;
    }

    /**
     * Alias de `params($key)` : valeur d'un paramètre de route.
     */
    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function body(?string $key = null, $default = null)
    {
        // Lazy loading : on ne parse qu'une seule fois
        if (!is_array($this->body)) {
            $input = is_string($this->body) ? $this->body : (string) file_get_contents('php://input');
            $decoded = json_decode($input, true);
            // Si JSON invalide ou vide, on utilise $_POST (pour les requêtes traditionnelles)
            $this->body = is_array($decoded) ? $decoded : ($this->post() ?: []);
        }

        if ($key === null) {
            return $this->body;
        }

        return $this->body[$key] ?? $default;
    }

    public function json()
    {
        return $this->body();
    }

    public function query(?string $key = null, $default = null)
    {
        $query = $this->query ?? $_GET;

        if ($key === null) {
            return $query;
        }
        return $query[$key] ?? $default;
    }

    public function query_string()
    {
        return (string) $this->server('QUERY_STRING', '');
    }

    public function post()
    {
        return $this->postData ?? $_POST;
    }

    public function getBearerToken()
    {
        $headers = $this->getHeader('Authorization');
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    public function method()
    {
        return (string) $this->server('REQUEST_METHOD', 'GET');
    }

    public function uri()
    {
        return (string) $this->server('REQUEST_URI', '/');
    }

    public function pathInfo()
    {
        $pathInfo = $this->server('PATH_INFO');

        return is_string($pathInfo) ? $pathInfo : null;
    }

    public function path()
    {
        $uri = parse_url($this->uri(), PHP_URL_PATH);

        return rtrim(is_string($uri) ? $uri : '/', '/');
    }

    public function getHeader($key = null)
    {
        $headers = $this->headers ?? $this->headersCache ??= $this->readHeaders();

        if ($key !== null) {
            $normalized = array_change_key_case($headers, CASE_LOWER);

            // `user_agent` (convention PHP) et `user-agent` (convention HTTP)
            // désignent le même en-tête.
            foreach ([strtolower($key), strtolower(str_replace('_', '-', $key))] as $candidate) {
                if (array_key_exists($candidate, $normalized)) {
                    return $normalized[$candidate];
                }
            }

            return null;
        }

        return $headers;
    }

    public function header($key = null)
    {
        return $this->getHeader($key);
    }

    /**
     * En-têtes lus depuis le SAPI, avec repli sur $_SERVER (CLI, tests).
     *
     * @return array<string, string>
     */
    private function readHeaders(): array
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];

        if (is_array($headers) && $headers !== []) {
            return $headers;
        }

        $headers = [];
        foreach ($this->server() as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                continue;
            }
            if (str_starts_with($name, 'HTTP_')) {
                $headers[str_replace('_', '-', ucwords(strtolower(substr($name, 5)), '_'))] = $value;
            }
        }

        foreach (['CONTENT_TYPE' => 'Content-Type', 'CONTENT_LENGTH' => 'Content-Length'] as $key => $header) {
            $value = $this->server($key);
            if (is_string($value) && !isset($headers[$header])) {
                $headers[$header] = $value;
            }
        }

        return $headers;
    }

    public function cookie($key = null)
    {
        $cookies = $this->cookies ?? $_COOKIE;

        if ($key) {
            return $cookies[$key] ?? null;
        }
        return $cookies;
    }

    public function input(?string $key = null, $default = null)
    {
        $data = array_merge($this->body(), $this->post(), $this->query());

        if ($key) {
            return $data[$key] ?? $default;
        }

        return $data;
    }

    public function form()
    {
        return $this->post();
    }

    public function file($key = null)
    {
        $files = $this->files ?? $_FILES;

        if ($key) {
            return $files[$key] ?? null;
        }
        return $files;
    }

    /**
     * Récupère les données de session
     * @return array
     */
    public function getSession(): array|null
    {
        return $_SESSION ?? null;
    }

    /**
     * Récupère une valeur spécifique de la session
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function session($key = null, $default = null)
    {
        if ($key) {
            return $_SESSION[$key] ?? $default;
        }
        return $_SESSION;
    }

    /**
     * Définit une valeur dans la session.
     */
    public function setSessionValue(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Summary of isSocialBot
     * @return bool|int
     */
    public function isSocialBot()
    {
        $userAgent = (string) $this->userAgent();
        $isSocialBot = preg_match('/(facebook|twitter|linkedin|whatsapp|telegram)/i', $userAgent);
        return $isSocialBot;
    }

    public function userAgent()
    {
        return (string) $this->server('HTTP_USER_AGENT', '');
    }

    /**
     * Summary of getClientIP
     */
    public function getClientIP(array $trustedProxies = []): string
    {
        $remote = (string) $this->server('REMOTE_ADDR');

        if (!in_array($remote, $trustedProxies, true)) {
            return $remote;
        }

        $chain = array_reverse(array_map('trim', explode(',', (string) $this->server('HTTP_X_FORWARD_FOR'))));

        foreach ($chain as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP) && !in_array($ip, $trustedProxies, true)) {
                return $ip;
            }
        }
        return $remote;
    }

    /**
     * Summary of checkRequestOrigin
     * @param mixed $allowedDomains
     * @return bool
     */
    public function checkRequestOrigin($allowedDomains = null): bool
    {
        if (empty($allowedDomains))
            return true;

        $origin = $this->server('HTTP_ORIGIN') ?? $this->server('HTTP_REFERER') ?? null;
        if (!$origin)
            return false;

        $domain = parse_url((string) $origin, PHP_URL_HOST);
        return in_array($domain, $allowedDomains, true);
    }
}
