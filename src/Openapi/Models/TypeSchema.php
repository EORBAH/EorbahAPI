<?php

namespace Eorbahapi\Openapi\Models;

use Eorbahapi\Dependency\ParameterDescriptor;
use Eorbahapi\Params\Param;
use Svinx\Validator\Typing\Type;

/**
 * Schéma JSON d'un type déclaré, enrichi des contraintes de l'attribut.
 *
 * C'est le pont direct entre Svinx\Validator et OpenAPI : un paramètre
 * `#[Query(gte: 1, le: 100)] int $limit` devient
 * `{type: integer, minimum: 1, maximum: 100}`.
 */
final class TypeSchema
{
    /**
     * @return array<string, mixed>
     */
    public static function from(ParameterDescriptor $descriptor, ?Param $attr = null): array
    {
        try {
            $type = Type::fromReflection($descriptor->type);

            if ($attr !== null) {
                $type = $type->withConstraints($attr);
            }

            return $type->schema();
        } catch (\Throwable) {
            // Type non supporté par Svinx (classe arbitraire) : on documente un
            // objet générique plutôt que de faire échouer la génération.
            return [];
        }
    }
}
