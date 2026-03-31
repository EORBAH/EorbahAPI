<?php

namespace EorBah545\Eorbahapi\datastructures;
// Placeholder interne pour les valeurs par défaut
class DefaultPlaceholder
{
    // Internal placeholder, perhaps singleton
    private static $instance = null;

    private function __construct()
    {
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}