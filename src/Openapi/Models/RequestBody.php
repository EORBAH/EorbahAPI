<?php

namespace Eorbahapi\Openapi\Models;

use Eorbahapi\Datastructures\UploadFile;
use Eorbahapi\Dependency\ParameterDescriptor;
use Svinx\Validator\BaseModel;

/**
 * `requestBody` OpenAPI construit depuis un paramètre `#[Body]`, `#[Form]` ou
 * `#[File]`.
 *
 *  - un modèle Svinx devient une référence vers `components.schemas` ;
 *  - un fichier devient `type: string, format: binary` ;
 *  - une liste de fichiers devient un tableau de binaires ;
 *  - un scalaire / tableau conserve le schéma de Svinx.
 */
final class RequestBody
{
    public function __construct(
        private readonly ParameterDescriptor $descriptor,
        private readonly string $mediaType = 'application/json',
        private readonly ?SchemaRegistry $registry = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $body = [
            'content' => [$this->mediaType => ['schema' => $this->schema()]],
            'required' => !$this->descriptor->hasDefault && !$this->descriptor->allowsNull,
        ];

        $description = $this->descriptor->param?->description;

        if ($description !== null) {
            $body['description'] = $description;
        }

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        $descriptor = $this->descriptor;

        if ($descriptor->typeName !== null && is_subclass_of($descriptor->typeName, BaseModel::class)) {
            return $this->registry?->reference($descriptor->typeName) ?? ['type' => 'object'];
        }

        if ($this->mediaType === 'multipart/form-data') {
            return $this->uploadSchema();
        }

        $schema = TypeSchema::from($descriptor);

        return $this->registry !== null ? $this->registry->normalize($schema) : $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function uploadSchema(): array
    {
        $type = $this->descriptor->type;

        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            if (is_a($type->getName(), UploadFile::class, true)) {
                return ['type' => 'string', 'format' => 'binary'];
            }
        }

        if ($type instanceof \ReflectionNamedType && $type->isBuiltin() && $type->getName() === 'array') {
            return ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'binary']];
        }

        return ['type' => 'string', 'format' => 'binary'];
    }
}
