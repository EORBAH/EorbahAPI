<?php

namespace Eorbahapi\Dependency;

use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionNamedType;
use Eorbahapi\Request;
use Eorbahapi\Response;
use Eorbahapi\Exceptions\DependencyScopeError;

use Eorbahapi\Params\Depends;
use Eorbahapi\Params\Body;
use Eorbahapi\Params\Cookie;
use Eorbahapi\Params\File;
use Eorbahapi\Params\Form;
use Eorbahapi\Params\Header;
use Eorbahapi\Params\Path;
use Eorbahapi\Params\Query;
use Eorbahapi\params\Security;

class DependencyResolver extends Request 
{
    private array $container = [];

    public function __construct(
        private Request $request,
        private Response $response
    ) {}

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

        $dependsAttr = $this->getDependsAttribute($param);
        $queryAttr = $this->getQueryAttribute($param);
        $bodyAttr = $this->getBodyAttribute($param);
        $cookieAttr = $this->getCookieAttribute($param);
        $fileAttr = $this->getFileAttribute($param);
        $formAttr = $this->getFormAttribute($param);
        $headerAttr = $this->getHeaderAttribute($param);
        $pathAttr = $this->getPathAttribute($param);
        $securityAttr = $this->getSecurityAttribute($param);

        // Params gestions
        if ($dependsAttr) {
            return $this->resolveFromDependsAttribute($dependsAttr, $typeName);
        }

        if ($queryAttr) {
            return $this->resolveFromQueryAttribute($queryAttr, $typeName);
        }

        if ($bodyAttr) {
            return $this->resolveFromBodyAttribute($queryAttr, $typeName);
        }

        if ($cookieAttr) {
            return $this->resolveFromCookieAttribute($queryAttr, $typeName);
        }

        if ($fileAttr) {
            return $this->resolveFromFileAttribute($queryAttr, $typeName);
        }

        if ($formAttr) {
            return $this->resolveFromFormAttribute($queryAttr, $typeName);
        }

        if ($headerAttr) {
            return $this->resolveFromHeaderAttribute($queryAttr, $typeName);
        }

        if ($pathAttr) {
            return $this->resolveFromPathAttribute($queryAttr, $typeName);
        }

        if ($securityAttr) {
            return $this->resolveFromSecurityAttribute($queryAttr, $typeName);
        }

        // 2. Paramètre fourni par la requête (URL ou body)
        $value = $this->extractProvidedValue($param, $providedParams);
        
        if ($value !== null) {
            return $this->castValue($value, $type);
        }

        // 3. Request / Response (injection automatique)
        if ($typeName === Request::class) {
            return $this->request;
        }
        if ($typeName === Response::class) {
            return $this->response;
        }

        // 4. Service enregistré dans le conteneur
        if ($typeName && isset($this->container[$typeName])) {
            return $this->container[$typeName];
        }

        // 5. Instanciation automatique (constructeur sans paramètres requis) 
        if ($typeName && class_exists($typeName)) {
            $reflectionClass = new \ReflectionClass($typeName);
            $constructor = $reflectionClass->getConstructor();
            // Pas de constructeur, ou constructeur sans paramètres obligatoires
            if (!$constructor || $constructor->getNumberOfRequiredParameters() === 0) {
                $instance = $reflectionClass->newInstance();
                $this->container[$typeName] = $instance; // mise en cache (singleton)
                return $instance;
            }
        }

        // 6. Valeur par défaut du paramètre
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        // 7. Null autorisé
        if ($type && $type->allowsNull()) {
            return null;
        }

        throw new DependencyScopeError(
            "Impossible de résoudre le paramètre '{$paramName}' de type '{$typeName}'"
        );
    }

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

    private function castValue(mixed $value, ?\ReflectionType $type): mixed
    {
        if ($type === null || ($type->allowsNull() && $value === null)) {
            return $value;
        }
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin() === false) {
            return $value;
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

    private function getBodyAttribute(ReflectionParameter $param): ?Body
    {
        $attrs = $param->getAttributes(Body::class);
        return empty($attrs) ? null : $attrs[0]->newInstance();
    }

    private function getCookieAttribute(ReflectionParameter $param): ?Cookie
    {
        $attrs = $param->getAttributes(Cookie::class);
        return empty($attrs) ? null : $attrs[0]->newInstance();
    }

    private function getFileAttribute(ReflectionParameter $param): ?File
    {
        $attrs = $param->getAttributes(File::class);
        return empty($attrs) ? null : $attrs[0]->newInstance();
    }

    private function getFormAttribute(ReflectionParameter $param): ?Form
    {
        $attrs = $param->getAttributes(Form::class);
        return empty($attrs) ? null : $attrs[0]->newInstance();
    }

    private function getHeaderAttribute(ReflectionParameter $param): ?Header
    {
        $attrs = $param->getAttributes(Header::class);
        return empty($attrs) ? null : $attrs[0]->newInstance();
    }

    private function getPathAttribute(ReflectionParameter $param): ?Path
    {
        $attrs = $param->getAttributes(Path::class);
        return empty($attrs) ? null : $attrs[0]->newInstance();
    }

    private function getQueryAttribute(ReflectionParameter $param): ?Query
    {
        $attrs = $param->getAttributes(Query::class);
        return empty($attrs) ? null : $attrs[0]->newInstance();
    }

    private function getSecurityAttribute(ReflectionParameter $param): ?Security
    {
        $attrs = $param->getAttributes(Session::class);
        return empty($attrs) ? null : $attrs[0]->newInstance();
    }

    public function resolveFromQueryAttribute(Query $attr, ?string $typeName): mixed {}
    public function resolveFromBodyAttribute(Body $attr, ?string $typeName): mixed {}
    public function resolveFromCookieAttribute(Cookie $attr, ?string $typeName): mixed {}
    public function resolveFromFileAttribute(File $attr, ?string $typeName): mixed {}
    public function resolveFromFormAttribute(Form $attr, ?string $typeName): mixed {}
    public function resolveFromHeaderAttribute(Header $attr, ?string $typeName): mixed {}
    public function resolveFromSecurityAttribute(Security $attr, ?string $typeName): mixed {}
    public function resolveFromPathAttribute(Path $attr, ?string $typeName): mixed {}

    
    private function resolveFromDependsAttribute(Depends $attr, ?string $typeName): mixed
    {
        $dependency = $attr->dependency;

        if ($dependency === null) {
            $dependency = $typeName;
        }

        if ($dependency === null) {
            throw new DependencyScopeError(
                'La dependance doit être spécifiée.'
            );
        }

        if (class_exists($dependency)) {
            return $this->resolveClass(
                $dependency,
                $attr->args
            );
        }

        if (is_array($dependency)) {
            return $this->resolveCallable(
                $dependency,
                $attr->args
            );
        }

        if (function_exists($dependency)) {
            return $this->resolveCallable(
                $dependency,
                $attr->args
            );
        }

        throw new DependencyScopeError("Impossible de résoudre la dépendance [$dependency]");
    }

    private function resolveClass(string $class, array $args): mixed {
        if (is_subclass_of($class, DependencyInterface::class)) {
            $instance = new $class(...$args);
            return $instance->resolve($this->request, $this->response);
        }
        if (isset($this->container[$class])) {
            return $this->container[$class];
        }
        return new $class(...$args);
    }

    private function resolveCallable(
        callable|array|string $callable,
        array $args = []
    ): mixed {

        $resolvedArgs = $this->resolve(
            $callable,
            $args
        );

        return $callable(...$resolvedArgs);
    }
}