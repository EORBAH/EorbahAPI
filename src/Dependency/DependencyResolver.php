<?php

namespace Eorbahapi\Dependency;

use ReflectionAttribute;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use Svinx\Validator\BaseModel;
use Svinx\Validator\Typing\Type;
use Svinx\Validator\ValidationError;

use Eorbahapi\Datastructures\UploadFile;
use Eorbahapi\Exceptions\DependencyScopeError;
use Eorbahapi\Exceptions\RequestValidationError;
use Eorbahapi\Params\Depends;
use Eorbahapi\Params\Header;
use Eorbahapi\Params\Param;
use Eorbahapi\Params\ParamTypes;
use Eorbahapi\Params\Security;
use Eorbahapi\Request;
use Eorbahapi\Response;

/**
 * Résout les arguments des callables de route.
 *
 * Trois familles de paramètres sont prises en charge, dans cet ordre :
 *
 *  1. les attributs de dépendance : `#[Depends]` et `#[Security]` ;
 *  2. les attributs de source : `#[Path]`, `#[Query]`, `#[Header]`, `#[Cookie]`,
 *     `#[Body]`, `#[Form]`, `#[File]`. La valeur brute est lue dans la requête
 *     puis validée par Svinx\Validator : le type déclaré du paramètre construit
 *     un `Type` (en mode laxiste, les valeurs HTTP étant des chaînes) et les
 *     contraintes de l'attribut (`gt`, `min_length`, `pattern`, `choices`...)
 *     lui sont ajoutées via `Type::withConstraints()`. Une `ValidationError` de
 *     Svinx est convertie en `RequestValidationError` (HTTP 422) ;
 *  3. les injections classiques : valeur fournie par le routeur, `Request` /
 *     `Response`, service du conteneur, instanciation automatique, valeur par
 *     défaut.
 *
 * La réflexion des paramètres est faite **une seule fois par callback** :
 * `describe()` produit des `ParameterDescriptor` réutilisés par chaque requête
 * (et par le générateur OpenAPI). C'est le premier étage du cache interne
 * d'EorbahAPI, indépendant du cache fichier activé par `EorbahAPI(cache: true)`.
 */
class DependencyResolver
{
    /** @var array<string, mixed> Services enregistrés (et instances mises en cache). */
    private array $container = [];

    /** @var array<string, list<ParameterDescriptor>> Descripteurs par callback. */
    private static array $descriptors = [];

    public function __construct(
        private Request $request,
        private Response $response
    ) {}

    public function set(string $id, mixed $value): void
    {
        $this->container[$id] = $value;
    }

    /**
     * Réassocie le resolver à une nouvelle requête / réponse, sans perdre les
     * services déjà enregistrés (utilisé par `EorbahAPI::handle()`).
     */
    public function setRequestResponse(Request $request, Response $response): void
    {
        $this->request = $request;
        $this->response = $response;
    }

    // ------------------------------------------------------------------
    // Description des paramètres (réflexion mise en cache)
    // ------------------------------------------------------------------

    /**
     * Décrit les paramètres d'un callback, une seule fois par processus.
     *
     * Les descripteurs ne dépendent pas de la requête : `Request` et `Response`
     * sont injectés au moment de la résolution. Le générateur OpenAPI
     * (`Eorbahapi\Openapi`) s'appuie sur la même description, si bien que la
     * documentation et l'exécution ne peuvent pas diverger.
     *
     * @return list<ParameterDescriptor>
     */
    public static function describe(callable $callback): array
    {
        $key = self::callbackKey($callback);

        return self::$descriptors[$key] ??= self::buildDescriptors($callback);
    }

    /**
     * Identifiant stable d'un callback, utilisé comme clé de cache.
     */
    public static function callbackKey(callable $callback): string
    {
        if (is_array($callback)) {
            $owner = is_object($callback[0]) ? $callback[0]::class : (string) $callback[0];

            return $owner . '::' . $callback[1];
        }

        if ($callback instanceof \Closure) {
            $reflection = new ReflectionFunction($callback);
            $file = $reflection->getFileName() ?: 'internal';

            return 'closure:' . $file . ':' . $reflection->getStartLine() . ':' . $reflection->getEndLine();
        }

        if (is_object($callback)) {
            return 'invokable:' . $callback::class;
        }

        if (is_string($callback) && str_contains($callback, '::')) {
            return $callback;
        }

        return 'function:' . (string) $callback;
    }

    /**
     * Fichier source d'un callback (null pour une fonction interne).
     */
    public static function callbackFile(callable $callback): ?string
    {
        try {
            $file = self::reflect($callback)->getFileName();
        } catch (\ReflectionException) {
            return null;
        }

        return $file !== false ? $file : null;
    }

    /**
     * @return list<ParameterDescriptor>
     */
    private static function buildDescriptors(callable $callback): array
    {
        $reflection = self::reflect($callback);
        $descriptors = [];

        foreach ($reflection->getParameters() as $param) {
            $hasDefault = $param->isDefaultValueAvailable();

            $descriptors[] = new ParameterDescriptor(
                name: $param->getName(),
                position: $param->getPosition(),
                depends: self::attribute($param, Depends::class),
                param: self::paramAttribute($param),
                type: $param->getType(),
                typeName: self::declaredClass($param),
                hasDefault: $hasDefault,
                default: $hasDefault ? $param->getDefaultValue() : null,
                allowsNull: $param->allowsNull(),
                expectsModel: self::expectsModel($param),
                expectsWholePayload: self::expectsWholePayload($param),
            );
        }

        return $descriptors;
    }

    private static function reflect(callable $callback): ReflectionFunction|ReflectionMethod
    {
        if (is_array($callback)) {
            return new ReflectionMethod($callback[0], $callback[1]);
        }

        if ($callback instanceof \Closure) {
            return new ReflectionFunction($callback);
        }

        if (is_object($callback)) {
            return new ReflectionMethod($callback, '__invoke');
        }

        if (is_string($callback) && str_contains($callback, '::')) {
            return new ReflectionMethod($callback);
        }

        return new ReflectionFunction($callback);
    }

    // ------------------------------------------------------------------
    // Résolution d'une requête
    // ------------------------------------------------------------------

    /**
     * Résout les arguments pour le callable donné.
     *
     * @param callable $callback
     * @param array    $providedParams Paramètres nommés ou indexés (ex: segments d'URL, body JSON-RPC)
     * @return array
     */
    public function resolve(callable $callback, array $providedParams = []): array
    {
        $args = [];

        foreach (self::describe($callback) as $descriptor) {
            $args[] = $this->resolveParameter($descriptor, $providedParams);
        }

        return $args;
    }

    private function resolveParameter(ParameterDescriptor $descriptor, array $providedParams): mixed
    {
        // 1. Dépendances explicites.
        if ($descriptor->depends !== null) {
            return $this->resolveFromDependsAttribute($descriptor->depends, $descriptor->typeName);
        }

        // 2. Paramètres de requête, validés par Svinx\Validator.
        if ($descriptor->param !== null) {
            return $this->resolveFromParam($descriptor->param, $descriptor, $providedParams);
        }

        // 3. Paramètre fourni par la requête (URL ou body JSON-RPC).
        $value = $this->extractProvidedValue($descriptor, $providedParams);

        if ($value !== null) {
            return $this->castValue($value, $descriptor->type);
        }

        // 4. Request / Response (injection automatique).
        if ($descriptor->typeName === Request::class) {
            return $this->request;
        }
        if ($descriptor->typeName === Response::class) {
            return $this->response;
        }

        // 5. Service enregistré dans le conteneur.
        if ($descriptor->typeName !== null && array_key_exists($descriptor->typeName, $this->container)) {
            return $this->container[$descriptor->typeName];
        }

        // 6. Instanciation automatique (constructeur sans paramètres requis).
        if ($descriptor->typeName !== null && class_exists($descriptor->typeName)) {
            $reflectionClass = new \ReflectionClass($descriptor->typeName);
            $constructor = $reflectionClass->getConstructor();

            if ($constructor === null || $constructor->getNumberOfRequiredParameters() === 0) {
                $instance = $this->container[$descriptor->typeName] ??= $reflectionClass->newInstance();
                return $instance;
            }
        }

        // 7. Valeur par défaut du paramètre.
        if ($descriptor->hasDefault) {
            return $descriptor->default;
        }

        // 8. Null autorisé.
        if ($descriptor->allowsNull) {
            return null;
        }

        throw new DependencyScopeError(
            "Impossible de résoudre le paramètre '{$descriptor->name}' de type '{$descriptor->typeName}'"
        );
    }

    // ------------------------------------------------------------------
    // Attributs de source : Path, Query, Header, Cookie, Body, Form, File
    // ------------------------------------------------------------------

    /**
     * Résout un paramètre déclaré par un attribut de `Eorbahapi\Params`.
     *
     * Lit la valeur brute à l'emplacement indiqué, puis la valide avec le type
     * déclaré du paramètre et les contraintes portées par l'attribut.
     *
     * @param array $providedParams Paramètres de route déjà extraits par le routeur.
     */
    public function resolveFromParam(Param $attr, ParameterDescriptor $descriptor, array $providedParams = []): mixed
    {
        $location = $attr->location();

        if ($location === ParamTypes::SECURITY) {
            return $this->resolveSecurityDependency($attr, $descriptor);
        }

        $name = $this->wireName($attr, $descriptor);
        [$present, $raw] = $this->extractParamValue($location, $name, $descriptor, $providedParams);

        if (!$present) {
            return $this->fallbackValue($attr, $descriptor, $location, $name);
        }

        try {
            return $this->typeFor($attr, $descriptor)->validate($raw, [], $this->label($location, $name));
        } catch (ValidationError $error) {
            throw RequestValidationError::fromValidationError($location->value, $name, $error);
        }
    }

    /**
     * Nom du paramètre tel qu'il apparaît dans la requête.
     */
    private function wireName(Param $attr, ParameterDescriptor $descriptor): string
    {
        $name = $attr->nameFor($descriptor->name);

        // Convention FastAPI : `user_agent` côté PHP lit `User-Agent`.
        return $attr instanceof Header ? str_replace('_', '-', $name) : $name;
    }

    /**
     * Lit la valeur brute du paramètre à son emplacement.
     *
     * @return array{0: bool, 1: mixed} Présence puis valeur brute.
     */
    private function extractParamValue(
        ParamTypes $location,
        string $name,
        ParameterDescriptor $descriptor,
        array $providedParams
    ): array {
        switch ($location) {
            case ParamTypes::PATH:
                if (array_key_exists($name, $providedParams)) {
                    return [true, $providedParams[$name]];
                }

                $value = $this->request->params($name);
                return $value === null ? [false, null] : [true, $value];

            case ParamTypes::QUERY:
                return $this->lookup($this->request->query(), $name);

            case ParamTypes::HEADER:
                return $this->lookupHeader($name);

            case ParamTypes::COOKIE:
                return $this->lookup($this->request->cookie(), $name);

            case ParamTypes::BODY:
                return $this->lookupPayload($this->request->body(), $name, $descriptor);

            case ParamTypes::FORM:
                return $this->lookupPayload($this->request->post(), $name, $descriptor);

            case ParamTypes::FILE:
                return $this->lookupFile($name, $descriptor);
        }

        return [false, null];
    }

    /**
     * @param mixed $source
     * @return array{0: bool, 1: mixed}
     */
    private function lookup(mixed $source, string $name): array
    {
        if (!is_array($source) || !array_key_exists($name, $source)) {
            return [false, null];
        }

        return [true, $source[$name]];
    }

    /**
     * Recherche insensible à la casse, avec conversion `_` -> `-`.
     *
     * @return array{0: bool, 1: mixed}
     */
    private function lookupHeader(string $name): array
    {
        $headers = $this->request->getHeader();

        if (!is_array($headers)) {
            return [false, null];
        }

        $normalized = array_change_key_case($headers, CASE_LOWER);

        foreach ([strtolower($name), strtolower(str_replace('_', '-', $name))] as $key) {
            if (array_key_exists($key, $normalized)) {
                return [true, $normalized[$key]];
            }
        }

        return [false, null];
    }

    /**
     * Corps / formulaire : le modèle reçoit tout le payload, un scalaire reçoit
     * uniquement son champ (ou tout le payload pour `array` / `mixed`).
     *
     * @return array{0: bool, 1: mixed}
     */
    private function lookupPayload(mixed $payload, string $name, ParameterDescriptor $descriptor): array
    {
        if (!is_array($payload)) {
            return [false, null];
        }

        if ($descriptor->expectsModel) {
            return [true, $payload];
        }

        if (array_key_exists($name, $payload)) {
            return [true, $payload[$name]];
        }

        if ($descriptor->expectsWholePayload) {
            return [true, $payload];
        }

        return [false, null];
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function lookupFile(string $name, ParameterDescriptor $descriptor): array
    {
        $files = $this->request->file();

        if (!is_array($files) || !array_key_exists($name, $files)) {
            return [false, null];
        }

        return [true, $this->buildUploadValue($files[$name], $descriptor, $name)];
    }

    /**
     * Convertit une entrée de `$_FILES` en `UploadFile` lorsque le type déclaré
     * le demande ; sinon l'entrée brute est transmise telle quelle.
     */
    private function buildUploadValue(mixed $entry, ParameterDescriptor $descriptor, string $name): mixed
    {
        if (!is_array($entry) || !array_key_exists('tmp_name', $entry)) {
            // Téléversement multiple : tableau d'entrées, conservé tel quel.
            return $entry;
        }

        $class = $descriptor->typeName;

        if ($class === null || !is_a($class, UploadFile::class, true)) {
            return $entry;
        }

        $error = $entry['error'] ?? UPLOAD_ERR_OK;

        if ($error !== UPLOAD_ERR_OK) {
            throw RequestValidationError::at(
                ParamTypes::FILE->value,
                $name,
                "Le fichier '$name' n'a pas été téléversé correctement (code $error).",
                'file'
            );
        }

        $handle = is_string($entry['tmp_name']) && $entry['tmp_name'] !== ''
            ? @fopen($entry['tmp_name'], 'rb')
            : false;

        if ($handle === false) {
            throw RequestValidationError::at(
                ParamTypes::FILE->value,
                $name,
                "Le fichier '$name' est illisible.",
                'file'
            );
        }

        return new UploadFile(
            (string) ($entry['name'] ?? $name),
            (string) ($entry['type'] ?? 'application/octet-stream'),
            $handle
        );
    }

    // ------------------------------------------------------------------
    // Validation Svinx\Validator
    // ------------------------------------------------------------------

    /**
     * Type Svinx du paramètre : type déclaré + contraintes de l'attribut.
     *
     * Le mode laxiste active la coercition des scalaires ("42" -> 42), les
     * valeurs HTTP étant toujours des chaînes.
     */
    private function typeFor(Param $attr, ParameterDescriptor $descriptor): Type
    {
        return Type::fromReflection($descriptor->type)
            ->lax()
            ->withConstraints($attr);
    }

    /**
     * Valeur utilisée lorsque le paramètre est absent : défaut de l'attribut,
     * puis défaut PHP, puis null si le type l'autorise.
     */
    private function fallbackValue(
        Param $attr,
        ParameterDescriptor $descriptor,
        ParamTypes $location,
        string $name
    ): mixed {
        if ($attr->default !== null || $attr->default_factory !== null) {
            return $attr->getDefault();
        }

        if ($descriptor->hasDefault) {
            return $descriptor->default;
        }

        if ($descriptor->allowsNull) {
            return null;
        }

        throw RequestValidationError::at(
            $location->value,
            $name,
            "Le paramètre {$location->value} '$name' est requis.",
            'missing'
        );
    }

    private function label(ParamTypes $location, string $name): string
    {
        return sprintf("Le paramètre %s '%s'", $location->value, $name);
    }

    // ------------------------------------------------------------------
    // Dépendances : Depends et Security
    // ------------------------------------------------------------------

    /**
     * Attribut de source déclaré sur le paramètre (un seul autorisé).
     */
    private static function paramAttribute(ReflectionParameter $param): ?Param
    {
        $attributes = $param->getAttributes(Param::class, ReflectionAttribute::IS_INSTANCEOF);

        if ($attributes === []) {
            return null;
        }

        $found = [];
        foreach ($attributes as $attribute) {
            /** @var Param $instance */
            $instance = $attribute->newInstance();
            $found[$instance->location()->value] = $instance;
        }

        if (count($found) > 1) {
            throw new DependencyScopeError(sprintf(
                "Le paramètre '%s' déclare plusieurs sources (%s) : un seul attribut parmi "
                . 'Path, Query, Header, Cookie, Body, Form, File, Security est autorisé.',
                $param->getName(),
                implode(', ', array_keys($found))
            ));
        }

        return reset($found);
    }

    /**
     * Premier attribut d'une classe donnée sur le paramètre.
     */
    private static function attribute(ReflectionParameter $param, string $class): ?object
    {
        $attributes = $param->getAttributes($class, ReflectionAttribute::IS_INSTANCEOF);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }

    private function resolveFromDependsAttribute(Depends $attr, ?string $typeName): mixed
    {
        $dependency = $attr->dependency ?? $typeName;

        if ($dependency === null) {
            throw new DependencyScopeError(
                'La dependance doit être spécifiée.'
            );
        }

        if (is_array($dependency)) {
            return $this->resolveCallable($dependency, $attr->args);
        }

        if (class_exists($dependency)) {
            return $this->resolveClass($dependency, $attr->args);
        }

        if (function_exists($dependency)) {
            return $this->resolveCallable($dependency, $attr->args);
        }

        throw new DependencyScopeError("Impossible de résoudre la dépendance [$dependency]");
    }

    /**
     * Exécute le schéma de sécurité et retourne la valeur injectée au paramètre.
     */
    private function resolveSecurityDependency(Param $attr, ParameterDescriptor $descriptor): mixed
    {
        /** @var Security $attr */
        $dependency = $attr->dependency ?? $descriptor->typeName;

        if ($dependency === null) {
            throw new DependencyScopeError(sprintf(
                "Le paramètre '%s' doit préciser le schéma de sécurité à utiliser "
                . '(ex: #[Security(HTTPBearer::class)]).',
                $descriptor->name
            ));
        }

        if (is_array($dependency)) {
            return $this->resolveCallable($dependency);
        }

        if (class_exists($dependency)) {
            $instance = $this->resolveClass($dependency, []);

            // Un schéma de sécurité appelable (« __invoke ») produit la valeur :
            // on l'exécute au lieu d'injecter l'instance.
            return is_callable($instance) ? $this->resolveCallable($instance) : $instance;
        }

        if (function_exists($dependency)) {
            return $this->resolveCallable($dependency);
        }

        throw new DependencyScopeError("Impossible de résoudre la dépendance de sécurité [$dependency]");
    }

    private function resolveClass(string $class, array $args): mixed
    {
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
        $resolvedArgs = $this->resolve($callable, $args);

        return $callable(...$resolvedArgs);
    }

    // ------------------------------------------------------------------
    // Aides
    // ------------------------------------------------------------------

    private function extractProvidedValue(ParameterDescriptor $descriptor, array $providedParams): mixed
    {
        if (array_key_exists($descriptor->name, $providedParams)) {
            return $providedParams[$descriptor->name];
        }

        if (array_key_exists($descriptor->position, $providedParams)) {
            return $providedParams[$descriptor->position];
        }

        return null;
    }

    private function castValue(mixed $value, ?ReflectionType $type): mixed
    {
        if ($type === null || ($type->allowsNull() && $value === null)) {
            return $value;
        }

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin() === false) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'string' => (string) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'array' => (array) $value,
            default => $value,
        };
    }

    /**
     * Classe déclarée par le type du paramètre (null si type natif ou absent).
     */
    private static function declaredClass(ReflectionParameter $param): ?string
    {
        $type = $param->getType();

        return $type instanceof ReflectionNamedType && !$type->isBuiltin()
            ? $type->getName()
            : null;
    }

    /**
     * Le paramètre attend-il un modèle Svinx (tout le corps est alors utilisé) ?
     */
    private static function expectsModel(ReflectionParameter $param): bool
    {
        $class = self::declaredClass($param);

        return $class !== null && is_subclass_of($class, BaseModel::class);
    }

    /**
     * Le paramètre attend-il le payload complet (`array` ou `mixed`) ?
     */
    private static function expectsWholePayload(ReflectionParameter $param): bool
    {
        $type = $param->getType();

        if ($type === null) {
            return true;
        }

        if ($type instanceof ReflectionNamedType) {
            return $type->isBuiltin() && in_array($type->getName(), ['array', 'mixed', 'iterable'], true);
        }

        return false;
    }
}
