<?php

namespace EorBah545\Eorbahapi;

class Request {
    public $segments;
    private $session; // Propriété pour stocker la session

    public function __construct() {
        $this->segments = [];
        $this->session = [];
    }

    public function params($value = null) {
        if (is_array($value)) {
            $this->segments = $value;
        } elseif (is_string($value)) {
            return $this->segments[$value];
        }
        return $this->segments;
    }

    public function body() {
        return json_decode(file_get_contents("php://input"), true) ?? $_POST ?? [];
    }

    public function query(){
        return $_GET;
    }

    public function query_string(){
        return $_SERVER['QUERY_STRING'];
    }

    public function post() {
        return $_POST;
    }

    public function getBearerToken() {
        $headers = $this->getHeader('Authorization');
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    public function method(){
        return $_SERVER['REQUEST_METHOD'];
    }

    public function uri() {
        return $_SERVER['REQUEST_URI'];
    }

    public function path() {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        return rtrim($uri, '/');
    }

    public function getHeader($key = null) {
        $headers = getallheaders();
        if ($key) {
            return $headers[$key] ?? null;
        }
        return $headers;
    }

    public function cookie($key = null) {
        if ($key) {
            return $_COOKIE[$key] ?? null;
        }
        return $_COOKIE;
    }

    public function input($key, $default = null) {
        $data = array_merge($this->body(), $_POST, $_GET);
        return $data[$key] ?? $default;
    }
    
    public function FormData() {
        return $this->post();
    }

    public function File($key = null) {
        if ($key) {
            return $_FILES[$key] ?? null;
        }
        return $_FILES;
    }

    /**
     * Définit les données de session (appelé par SessionMiddleware)
     * @param array $session
     */
    public function setSession(array $session) {
        $this->session = $session;
    }

    /**
     * Récupère les données de session
     * @return array
     */
    public function getSession(): array {
        return $this->session;
    }

    /**
     * Récupère une valeur spécifique de la session
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function session(string $key, $default = null) {
        return $this->session[$key] ?? $default;
    }
}