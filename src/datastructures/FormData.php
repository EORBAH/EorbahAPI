<?php

namespace EorBah545\Eorbahapi\datastructures;
// (interne, pour multipart/form-data)
class FormData
{
    private $data = [];

    public function __construct($data = []) {
        $this->data = $data;
    }

    /**
     * Summary of get
     * @param mixed $key
     */
    public function get($key) {
        return $this->data[$key] ?? null;
    }

    /**
     * Summary of set
     * @param mixed $key
     * @param mixed $value
     * @return void
     */
    public function set($key, $value) {
        $this->data[$key] = $value;
    }

    /**
     * Summary of all
     * @return array|mixed
     */
    public function all() {
        return $this->data;
    }

    /**
     * Summary of xssClean
     * @param mixed $data
     * @return array<array|string>|string
     */
    public function xssClean($value = null) {
        $data = $value ?? $this->data;
        if (is_array($data)) {
            return array_map([$this, 'xssClean'], $data);
        }

        return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Summary of sanitizeInput
     * @param array $data
     * @param array $rules
     * @return array
     */
    public function sanitizeInput(array $rules): array
    {
        $sanitized = [];

        foreach ($rules as $field => $rule) {
            $value = $this->data[$field] ?? null;
            if ($rule['type'] === 'string') {
                $value = (string) $value;
                if (isset($rule['max_length'])) {
                    $value = substr($value, 0, $rule['max_length']);
                }
            } elseif ($rule['type'] === 'int') {
                $value = filter_var($value, FILTER_VALIDATE_INT);
            } elseif ($rule['type'] === 'email') {
                $value = filter_var($value, FILTER_SANITIZE_EMAIL);
                $value = filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
            }

            if ($value !== null && ($rule['xss_clean'] ?? true)) {
                $value = $this->xssClean($value);
            }

            $sanitized[$field] = $value;
        }

        return $sanitized;
    }
}