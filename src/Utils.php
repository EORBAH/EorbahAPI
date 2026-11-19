<?php

namespace Eorbahapi;

/**
 * Petites fonctions partagées par le routeur, les adaptateurs et les runners.
 */
class Utils
{
    /**
     * Reconstruit un chemin concret à partir d'un gabarit de route et de
     * paramètres : `getRoutePath('/users/{id}', ['id' => 7])` → `/users/7`.
     *
     * Les paramètres manquants laissent le placeholder intact ; les valeurs
     * sont encodées pour l'URL (`rawurlencode`).
     *
     * @param array<string, mixed> $params
     */
    public static function getRoutePath(string $route, array $params = []): string
    {
        return (string) preg_replace_callback(
            '/\{([A-Za-z_][A-Za-z0-9_]*)(?::[A-Za-z_][A-Za-z0-9_]*)?\}/',
            static function (array $matches) use ($params): string {
                $name = $matches[1];

                if (!array_key_exists($name, $params)) {
                    return $matches[0];
                }

                $value = $params[$name];

                return is_scalar($value) || $value === null
                    ? rawurlencode((string) $value)
                    : $matches[0];
            },
            $route
        );
    }

    /**
     * Normalise un chemin : pas de slash final (racine = chaîne vide), comme
     * `APIRouter::normalizePath()`.
     */
    public static function normalizePath(string $path): string
    {
        return rtrim($path, '/');
    }

    /**
     * Indique si un callable est une closure asynchrone (retourne un
     * `Generator` ou une promesse) — utile aux adaptateurs qui doivent
     * attendre un résultat.
     */
    public static function isAsyncCallable(callable $callback): bool
    {
        try {
            if ($callback instanceof \Closure) {
                $reflection = new \ReflectionFunction($callback);
            } elseif (is_array($callback)) {
                $reflection = new \ReflectionMethod($callback[0], $callback[1]);
            } elseif (is_object($callback)) {
                $reflection = new \ReflectionMethod($callback, '__invoke');
            } else {
                $reflection = new \ReflectionFunction($callback);
            }
        } catch (\ReflectionException) {
            return false;
        }

        $type = $reflection->getReturnType();

        return $type instanceof \ReflectionNamedType
            && in_array($type->getName(), [\Generator::class, \Fiber::class, 'PromiseInterface', 'Promise'], true);
    }
}
