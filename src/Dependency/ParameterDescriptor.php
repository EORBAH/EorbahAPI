<?php

namespace Eorbahapi\Dependency;

use Eorbahapi\Params\Depends;
use Eorbahapi\Params\Param;
use Eorbahapi\Params\ParamTypes;

/**
 * Description figée d'un paramètre de route.
 *
 * La réflexion PHP est coûteuse : elle est faite une fois par callback, puis le
 * résultat est réutilisé pour toutes les requêtes suivantes (voir
 * `DependencyResolver::describe()`). Le même descripteur sert aussi au
 * générateur OpenAPI, qui n'a pas besoin d'un `Request` pour connaître la forme
 * d'une route.
 *
 *     foreach (DependencyResolver::describe($callback) as $descriptor) {
 *         if ($descriptor->isWireParameter()) { ... }
 *     }
 */
final class ParameterDescriptor
{
    public function __construct(
        /** Nom du paramètre PHP. */
        public readonly string $name,
        /** Position dans la signature. */
        public readonly int $position,
        /** Attribut #[Depends] éventuel. */
        public readonly ?Depends $depends,
        /** Attribut de source (#[Path], #[Query]…, #[Security]) éventuel. */
        public readonly ?Param $param,
        /** Type déclaré (conservé tel quel pour la coercition). */
        public readonly ?\ReflectionType $type,
        /** Classe déclarée par le type, ou null pour un type natif. */
        public readonly ?string $typeName,
        /** Le paramètre a-t-il une valeur par défaut PHP ? */
        public readonly bool $hasDefault,
        /** Cette valeur par défaut, si applicable. */
        public readonly mixed $default,
        /** Le type autorise-t-il null ? */
        public readonly bool $allowsNull,
        /** Le paramètre attend-il un modèle Svinx (corps complet) ? */
        public readonly bool $expectsModel,
        /** Le paramètre attend-il le payload complet (`array` / `mixed`) ? */
        public readonly bool $expectsWholePayload,
    ) {
    }

    /**
     * Le paramètre vient-il de la requête HTTP (corps, formulaire, fichier) ?
     */
    public function isBodyLike(): bool
    {
        return $this->param !== null && in_array(
            $this->param->location(),
            [
                ParamTypes::BODY,
                ParamTypes::FORM,
                ParamTypes::FILE,
            ],
            true
        );
    }
}
