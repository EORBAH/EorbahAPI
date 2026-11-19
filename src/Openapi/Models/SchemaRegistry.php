<?php

namespace Eorbahapi\Openapi\Models;

use Eorbahapi\Openapi\Constants;
use Svinx\Validator\BaseModel;

/**
 * Registre des schémas `components.schemas` d'une spécification OpenAPI.
 *
 * Les modèles Svinx sont la source de vérité : `BaseModel::model_schema()`
 * décrit déjà les champs, leurs alias, contraintes, valeurs par défaut et
 * champs requis. Le registre convertit récursivement les modèles rencontrés en
 * `$ref` pour qu'un modèle imbriqué dans un autre soit référencé, pas dupliqué.
 *
 *     $ref = $registry->reference(CreateUser::class);
 *     // ['$ref' => '#/components/schemas/CreateUser']
 */
final class SchemaRegistry
{
    /** @var array<string, array<string, mixed>> Nom => schéma. */
    private array $schemas = [];

    /** @var array<string, string> Classe => nom du composant. */
    private array $names = [];

    /** @var array<string, true> Classes en cours d'enregistrement (récursion). */
    private array $inProgress = [];

    public function __construct()
    {
        // Toutes les opérations peuvent répondre 422 : le schéma de l'erreur de
        // validation est donc toujours présent.
        $this->schemas[Constants::VALIDATION_ERROR_SCHEMA] = [
            'title' => Constants::VALIDATION_ERROR_SCHEMA,
            'type' => 'object',
            'properties' => [
                'detail' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'loc' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'msg' => ['type' => 'string'],
                            'type' => ['type' => 'string'],
                        ],
                        'required' => ['loc', 'msg', 'type'],
                    ],
                ],
            ],
            'required' => ['detail'],
        ];
    }

    /**
     * Référence vers le schéma d'une classe, en l'enregistrant au passage.
     *
     * @return array<string, mixed>
     */
    public function reference(string $class): array
    {
        return ['$ref' => Constants::REF_PREFIX . $this->register($class)];
    }

    /**
     * Normalise un schéma issu de Svinx pour OpenAPI : titres de modèles en
     * `$ref`, énumérations développées, dates typées.
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    public function normalize(array $schema): array
    {
        return $this->transform($schema);
    }

    /**
     * Nom du composant associé à une classe (l'enregistre si nécessaire).
     */
    public function register(string $class): string
    {
        $class = ltrim($class, '\\');

        if (isset($this->names[$class])) {
            return $this->names[$class];
        }

        $name = $this->uniqueName($class);
        $this->names[$class] = $name;

        // Un modèle qui se référence lui-même : le `$ref` est déjà valide, on
        // n'enregistre pas une seconde fois.
        if (isset($this->inProgress[$class])) {
            return $name;
        }

        $this->inProgress[$class] = true;

        try {
            $schema = $this->schemaFor($class);

            // Le `title` de premier niveau est un libellé de composant, pas un
            // type : seules les propriétés sont converties en `$ref`.
            if (isset($schema['properties']) && is_array($schema['properties'])) {
                $schema['properties'] = $this->transform($schema['properties']);
            }

            $this->schemas[$name] = $schema;
        } finally {
            unset($this->inProgress[$class]);
        }

        return $name;
    }

    /**
     * Bloc `components` de la spécification.
     *
     * @return array<string, mixed>
     */
    public function components(): array
    {
        if ($this->schemas === []) {
            return [];
        }

        $schemas = $this->schemas;
        ksort($schemas);

        return ['schemas' => $schemas];
    }

    /**
     * @return array<string, mixed>
     */
    private function schemaFor(string $class): array
    {
        if (is_subclass_of($class, BaseModel::class)) {
            try {
                return $class::model_schema();
            } catch (\Throwable) {
                return $this->objectFallback($class);
            }
        }

        if (enum_exists($class)) {
            return $this->enumSchema($class);
        }

        if (is_a($class, \DateTimeInterface::class, true)) {
            return [
                'type' => 'string',
                'format' => 'date-time',
                'title' => $this->shortName($class),
            ];
        }

        return $this->objectFallback($class);
    }

    /**
     * Remplace les titres de modèles par des `$ref` et développe les énumérations.
     *
     * `Type::schema()` produit `['type' => 'object', 'title' => FQCN]` pour un
     * modèle ; OpenAPI attend `['$ref' => '#/components/schemas/Nom']`.
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function transform(array $schema): array
    {
        if (isset($schema['title']) && is_string($schema['title']) && $schema['title'] !== '') {
            $title = $schema['title'];

            if (enum_exists($title)) {
                return $this->enumSchema($title);
            }

            if (class_exists($title) && is_subclass_of($title, BaseModel::class)) {
                return $this->reference($title);
            }

            if (class_exists($title) && is_a($title, \DateTimeInterface::class, true)) {
                return ['type' => 'string', 'format' => 'date-time', 'title' => $this->shortName($title)];
            }
        }

        return array_map(fn (mixed $value): mixed => is_array($value) ? $this->transform($value) : $value, $schema);
    }

    /**
     * @return array<string, mixed>
     */
    private function enumSchema(string $class): array
    {
        $values = [];

        foreach ($class::cases() as $case) {
            $values[] = $case instanceof \BackedEnum ? $case->value : $case->name;
        }

        return [
            'type' => $values !== [] && is_int($values[0]) ? 'integer' : 'string',
            'enum' => $values,
            'title' => $this->shortName($class),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function objectFallback(string $class): array
    {
        return ['type' => 'object', 'title' => $this->shortName($class)];
    }

    private function shortName(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }

    /**
     * Nom unique d'un composant : le nom court, ou le nom qualifié en cas de
     * collision entre deux classes homonymes.
     */
    private function uniqueName(string $class): string
    {
        $short = $this->shortName($class);

        if (!isset($this->schemas[$short]) && !in_array($short, $this->names, true)) {
            return $short;
        }

        $qualified = str_replace('\\', '.', ltrim($class, '\\'));

        if (!isset($this->schemas[$qualified]) && !in_array($qualified, $this->names, true)) {
            return $qualified;
        }

        return $qualified . '.' . substr(sha1($class), 0, 8);
    }
}
