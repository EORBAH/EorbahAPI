<?php

// src/DependencyResolver.php
namespace EorBah545\Eorbahapi;

use EorBah545\Eorbahapi\Attributes\Depends;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionNamedType;

class DependencyResolver
{
    private array $container = [];

    public function __construct(
        private Request $request,
        private Response $response
    ) {
    }

    public function set(string $id, mixed $value): void
    {
        $this->container[$id] = $value;
    }

    /**
     * Résout les arguments pour le callable donné.
     *
     * @param callable $callback
     * @param array    $providedParams Paramètres nommés ou indexés (ex: segments d'URL, body JSON-RPC)
     * @return array
     */
    public function resolve(callable $callback, array $providedParams = []): array
    {
        $reflection = is_array($callback)
            ? new ReflectionMethod($callback[0], $callback[1])
            : new ReflectionFunction($callback);

        $args = [];
        foreach ($reflection->getParameters() as $param) {
            $args[] = $this->resolveParameter($param, $providedParams);
        }
        return $args;
    }

    private function resolveParameter(ReflectionParameter $param, array $providedParams): mixed
    {
        $type = $param->getType();
        $typeName = $type instanceof ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : null;
        $paramName = $param->getName();

        // 1. Attribut #[Depends] (si utilisé)
        $dependsAttr = $this->getDependsAttribute($param);
        if ($dependsAttr) {
            return $this->resolveFromAttribute($dependsAttr, $typeName);
        }

        // 2. Paramètre fourni par la requête (nommé ou indexé)
        $value = $this->extractProvidedValue($param, $providedParams);
        if ($value !== null) {
            // Application du typage (cast)
            return $this->castValue($value, $type);
        }

        // 3. Dépendances système Request / Response
        if ($typeName === Request::class) {
            return $this->request;
        }
        if ($typeName === Response::class) {
            return $this->response;
        }

        // 4. Service depuis le conteneur (par nom de classe)
        if ($typeName && isset($this->container[$typeName])) {
            return $this->container[$typeName];
        }

        // 5. Valeur par défaut du paramètre
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        // 6. Null autorisé
        if ($type && $type->allowsNull()) {
            return null;
        }

        throw new \RuntimeException(
            "Impossible de résoudre le paramètre '{$paramName}' de type '{$typeName}'"
        );
    }

    /**
     * Extrait la valeur depuis $providedParams (par nom ou par position).
     * Retourne null si non trouvée.
     */
    private function extractProvidedValue(ReflectionParameter $param, array $providedParams): mixed
    {
        $name = $param->getName();
        if (array_key_exists($name, $providedParams)) {
            return $providedParams[$name];
        }
        $pos = $param->getPosition();
        if (array_key_exists($pos, $providedParams)) {
            return $providedParams[$pos];
        }
        return null;
    }

    /**
     * Caste une valeur en fonction du type hint du paramètre.
     */
    private function castValue(mixed $value, ?\ReflectionType $type): mixed
    {
        if ($type === null || $type->allowsNull() && $value === null) {
            return $value;
        }
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin() === false) {
            return $value; // on ne caste pas les types objet (ils seront injectés par le conteneur)
        }

        $typeName = $type->getName();
        switch ($typeName) {
            case 'int':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'array':
                return (array) $value;
            default:
                return $value;
        }
    }

    private function getDependsAttribute(ReflectionParameter $param): ?Depends
    {
        $attrs = $param->getAttributes(Depends::class);
        return empty($attrs) ? null : $attrs[0]->newInstance();
    }

    private function resolveFromAttribute(Depends $attr, ?string $typeName): mixed
    {
        $class = $attr->class ?? $typeName;
        if (!$class) {
            throw new \RuntimeException("La classe de dépendance doit être spécifiée.");
        }

        if (is_subclass_of($class, DependencyInterface::class)) {
            $instance = new $class(...$attr->args);
            return $instance->resolve($this->request, $this->response);
        }

        if (isset($this->container[$class])) {
            return $this->container[$class];
        }

        return new $class(...$attr->args);
    }
}