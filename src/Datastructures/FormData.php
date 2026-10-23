<?php

namespace Eorbahapi\Datastructures;

/**
 *  interne for multipart/form-data
 */
class FormData {
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

    public static function escape(mixed $value): mixed {
        if (is_array($value)) {
            return array_map([self::class, 'escape'], $value);
        }
        if (!is_string($value)) {
            return $value;
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
