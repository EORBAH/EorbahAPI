<?php

namespace Eorbahapi\Openapi\Models;

use Eorbahapi\Dependency\ParameterDescriptor;
use Eorbahapi\Params\Header;
use Eorbahapi\Params\ParamTypes;

/**
 * Paramètre OpenAPI (`in: path|query|header|cookie`) construit depuis un
 * `ParameterDescriptor` — donc depuis le type déclaré et l'attribut Svinx.
 *
 *     #[Query(gte: 1, le: 100)] int $limit = 10
 *     // => {name: limit, in: query, required: false, schema: {type: integer, minimum: 1, maximum: 100, default: 10}}
 */
final class Parameter
{
    public function __construct(
        private readonly ParameterDescriptor $descriptor,
        private readonly ?SchemaRegistry $registry = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $param = $this->descriptor->param;

        if ($param === null) {
            return [];
        }

        $location = $param->location()->in();
        $name = $this->wireName($param);

        $schema = TypeSchema::from($this->descriptor, $param);

        if ($this->registry !== null) {
            $schema = $this->registry->normalize($schema);
        }

        $description = $schema['description'] ?? $param->description;
        unset($schema['description']);

        $isPath = $location === ParamTypes::PATH->value;

        $required = $isPath
            || (!$this->descriptor->hasDefault && !$this->descriptor->allowsNull && $param->isRequired());

        if ($this->descriptor->hasDefault && $this->descriptor->default !== null) {
            $schema['default'] = $this->descriptor->default;
        }

        $parameter = [
            'name' => $name,
            'in' => $location,
            'required' => $required,
            'schema' => $schema === [] ? new \stdClass() : $schema,
        ];

        if ($description !== null) {
            $parameter['description'] = $description;
        }

        return $parameter;
    }

    /**
     * Nom du paramètre sur le fil, avec la convention FastAPI pour les en-têtes.
     */
    private function wireName(\Eorbahapi\Params\Param $param): string
    {
        $name = $param->nameFor($this->descriptor->name);

        return $param instanceof Header ? str_replace('_', '-', $name) : $name;
    }
}
