<?php

// src/DependencyResolver.php
namespace EorBah545\Eorbahapi;

use EorBah545\Eorbahapi\Attributes\Depends;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;

class DependencyResolver
{
    private array $container = [];

    public function __construct(
        private Request $request,
        private Response $response
    ) {
    }

    /**
     * Enregistre une dépendance globale (ex: PDO, Logger)
     */
    public function set(string $id, mixed $value): void
    {
        $this->container[$id] = $value;
    }

    /**
     * Résout les arguments pour le callable donné.
     */
    public function resolve(callable $callback): array
    {
        $reflection = is_array($callback)
            ? new ReflectionMethod($callback[0], $callback[1])
            : new ReflectionFunction($callback);

        $args = [];
        foreach ($reflection->getParameters() as $param) {
            $args[] = $this->resolveParameter($param);
        }
        return $args;
    }

    private function resolveParameter(ReflectionParameter $param): mixed
    {
        $type = $param->getType();
        $typeName = $type && !$type->isBuiltin() ? $type->getName() : null;

        // 1. Attribut #[Depends] explicite
        $dependsAttr = $this->getDependsAttribute($param);
        if ($dependsAttr) {
            return $this->resolveFromAttribute($dependsAttr, $typeName);
        }

        // 2. Type correspondant à Request ou Response
        if ($typeName === Request::class) {
            return $this->request;
        }
        if ($typeName === Response::class) {
            return $this->response;
        }

        // 3. Recherche dans le conteneur par type
        if ($typeName && isset($this->container[$typeName])) {
            return $this->container[$typeName];
        }

        // 4. Valeur par défaut si disponible
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        // 5. Null si le type autorise null
        if ($type && $type->allowsNull()) {
            return null;
        }

        throw new \RuntimeException(
            "Impossible de résoudre le paramètre '{$param->getName()}' de type '{$typeName}'"
        );
    }

    private function getDependsAttribute(ReflectionParameter $param): ?Depends
    {
        $attrs = $param->getAttributes(Depends::class);
        if (empty($attrs))
            return null;
        return $attrs[0]->newInstance();
    }

    private function resolveFromAttribute(Depends $attr, ?string $typeName): mixed
    {
        $class = $attr->class ?? $typeName;
        if (!$class) {
            throw new \RuntimeException("La classe de dépendance doit être spécifiée pour le paramètre.");
        }

        // Si la classe implémente DependencyInterface, on l'instancie et on appelle resolve()
        if (is_subclass_of($class, DependencyInterface::class)) {
            $instance = new $class(...$attr->args);
            return $instance->resolve($this->request, $this->response);
        }

        // Sinon, on vérifie si elle est dans le conteneur
        if (isset($this->container[$class])) {
            return $this->container[$class];
        }

        // Tentative d'instanciation simple
        return new $class(...$attr->args);
    }
}