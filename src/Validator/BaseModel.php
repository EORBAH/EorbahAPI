<?php

namespace Eorbahapi\Validator;

use Eorbahapi\Request;
use Eorbahapi\Exceptions\ValidationException;

class BaseModel extends Request
{
    /**
     * Définit les règles de validation du modèle.
     * Exemple :
     * return [
     *   'name' => Field::required()->minLength(3),
     *   'age' => Field::required()->min(18),
     * ];
     */
    public static function fields(): array
    {
        return [];
    }

    public function __construct()
    {
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        $body = $this->json();
        $errors = [];

        $declaredFields = static::fields();

        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }

            if ($property->getDeclaringClass()->getName() !== get_class($this)) {
                continue;
            }

            $name = $property->getName();
            $field = $declaredFields[$name] ?? null;
            $sourceName = $field?->getSourceName() ?? $name;

            if (array_key_exists($sourceName, $body)) {
                $value = $body[$sourceName];
                $type = $property->getType();

                try {
                    $value = $this->validateAndCast($name, $value, $type, $field);
                } catch (\InvalidArgumentException $e) {
                    $errors[$name] = $e->getMessage();
                    continue;
                }

                $this->$name = $value;
                continue;
            }

            if ($field !== null && $field->isRequired() && !$property->hasDefaultValue()) {
                $errors[$name] = "Le champ '$name' est requis.";
                continue;
            }

            if ($field !== null && $field->hasDefaultValue() && !$property->hasDefaultValue()) {
                $this->$name = $field->getDefaultValue();
                continue;
            }

            if (!$property->hasDefaultValue()) {
                $errors[$name] = "Le champ '$name' est requis.";
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    private function validateAndCast(string $fieldName, mixed $value, ?\ReflectionType $type, ?Field $field = null): mixed
    {
        if ($field !== null) {
            $value = $field->validate($value, $fieldName);
        }

        if ($type instanceof \ReflectionUnionType) {
            return $value;
        }

        if ($type instanceof \ReflectionNamedType) {
            return $this->validateNamedType($fieldName, $value, $type);
        }

        return $value;
    }

    private function validateNamedType(string $fieldName, mixed $value, \ReflectionNamedType $type): mixed
    {
        $typeName = $type->getName();
        $allowsNull = $type->allowsNull();

        if ($value === null) {
            if ($allowsNull) {
                return null;
            }
            throw new \InvalidArgumentException("Le champ '$fieldName' ne peut pas être null.");
        }

        switch ($typeName) {
            case 'string':
                if (!is_string($value)) {
                    throw new \InvalidArgumentException("Le champ '$fieldName' doit être une chaîne de caractères.");
                }
                return $value;

            case 'int':
                if (is_int($value)) {
                    return $value;
                }
                if (is_string($value) || is_float($value)) {
                    $intValue = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
                    if ($intValue !== null) {
                        return $intValue;
                    }
                }
                throw new \InvalidArgumentException("Le champ '$fieldName' doit être un entier valide.");

            case 'float':
                if (is_float($value) || is_int($value)) {
                    return (float) $value;
                }
                if (is_string($value)) {
                    $floatValue = filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
                    if ($floatValue !== null) {
                        return $floatValue;
                    }
                }
                throw new \InvalidArgumentException("Le champ '$fieldName' doit être un nombre décimal valide.");

            case 'bool':
                return $this->castToBool($fieldName, $value);

            default:
                return $value;
        }
    }

    private function castToBool(string $fieldName, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) && ($value === 0 || $value === 1)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $lower = strtolower($value);
            if (in_array($lower, ['true', '1', 'on', 'yes'], true)) {
                return true;
            }
            if (in_array($lower, ['false', '0', 'off', 'no', ''], true)) {
                return false;
            }
        }

        throw new \InvalidArgumentException("Le champ '$fieldName' doit être un booléen valide.");
    }
}