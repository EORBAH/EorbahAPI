<?php

namespace Eorbahapi;

class Request
{
    public $segments;
    private ?array $bodyCache = null;

    public function __construct()
    {
        $this->segments = [];
    }

    public function params($value = null)
    {
        if (is_array($value)) {
            $this->segments = $value;
        } elseif (is_string($value)) {
            return $this->segments[$value] ?? null;
        }
        return $this->segments;
    }

    public function body(?string $key = null, $default = null)
    {
        // Lazy loading : on ne parse qu'une seule fois
        if ($this->bodyCache === null) {
            $input = file_get_contents("php://input");
            $decoded = json_decode($input, true);
            // Si JSON invalide ou vide, on utilise $_POST (pour les requêtes traditionnelles)
            $this->bodyCache = is_array($decoded) ? $decoded : ($_POST ?: []);
        }

        if ($key === null) {
            return $this->bodyCache;
        }

        return $this->bodyCache[$key] ?? $default;
    }

    public function json() {
        return $this->body();
    }

    public function query(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $_GET;
        }
        return $_GET[$key] ?? $default;
    }

    public function query_string()
    {
        return $_SERVER['QUERY_STRING'];
    }

    public function post()
    {
        return $_POST;
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
        return $_SERVER['REQUEST_METHOD'];
    }

    public function uri()
    {
        return $_SERVER['REQUEST_URI'];
    }

    public function path()
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        return rtrim($uri, '/');
    }

    public function getHeader($key = null)
    {
        $headers = getallheaders();
        if ($key) {
            return $headers[$key] ?? null;
        }
        return $headers;
    }

    public function header($key = null)
    {
        return $this->getHeader($key);
    }

    public function cookie($key = null)
    {
        if ($key) {
            return $_COOKIE[$key] ?? null;
        }
        return $_COOKIE;
    }

    public function input($key, $default = null)
    {
        $data = array_merge($this->body(), $_POST, $_GET);
        return $data[$key] ?? $default;
    }

    public function FormData()
    {
        return $this->post();
    }

    public function File($key = null)
    {
        if ($key) {
            return $_FILES[$key] ?? null;
        }
        return $_FILES;
    }

    /**
     * Récupère les données de session
     * @return array
     */
    public function getSession(): array
    {
        return $_SESSION;
    }

    /**
     * Récupère une valeur spécifique de la session
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function session(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
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
    public function isSocialBot() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $isSocialBot = preg_match('/(facebook|twitter|linkedin|whatsapp|telegram)/i', $userAgent);
        return $isSocialBot;
    }

    public function userAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Summary of getClientIP
     */
    public function getClientIP() {
        if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
            return $_SERVER["HTTP_CLIENT_IP"];
        } elseif (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            return $_SERVER["HTTP_X_FORWARDED_FOR"];
        } else {
            return $_SERVER["REMOTE_ADDR"];
        }
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

        $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? null;
        if (!$origin)
            return false;

        $domain = parse_url($origin, PHP_URL_HOST);
        return in_array($domain, $allowedDomains, true);
    }
}