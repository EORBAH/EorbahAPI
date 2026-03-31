<?php

namespace EorBah545\Eorbahapi;

class Request {
    public $segments;

    public function params($value = null) {
        if ($value !== null) {
            $this->segments = $value;
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
     * Summary of requests
     * @param mixed $method
     * @param mixed $url
     * @param mixed $data
     * @param mixed $headers
     * @return void
     */
    public function requests($method, $url, $data = null, $headers = []) {}
    
}
