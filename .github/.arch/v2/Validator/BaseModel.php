<?php

namespace Eorbahapi\Validator;

use Eorbahapi\Request;
use Eorbahapi\Exceptions\ValidationException;

class BaseModel extends Request
{
    public function __construct()
    {
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        $body = $this->json(); // tableau complet du JSON

        $errors = [];

        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }

            // Ignore les propriétés déclarées dans une classe parente (Request, BaseModel, etc.)
            if ($property->getDeclaringClass()->getName() !== get_class($this)) {
                continue;
            }

            $name = $property->getName();

            // Vérification de la présence dans le body
            if (array_key_exists($name, $body)) {
                $value = $body[$name];
                $type = $property->getType();

                if ($type !== null) {
                    try {
                        $value = $this->validateAndCast($name, $value, $type);
                    } catch (\InvalidArgumentException $e) {
                        $errors[$name] = $e->getMessage();
                        continue; // on n'affecte pas la valeur invalide
                    }
                }

                $this->$name = $value;
            } else {
                // Clé absente → champ obligatoire si pas de valeur par défaut
                if ($property->hasDefaultValue()) {
                    // On laisse la valeur par défaut déjà définie dans la classe enfant
                    continue;
                } else {
                    $errors[$name] = "Le champ '$name' est requis.";
                }
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Valide et transtype la valeur selon le type PHP déclaré.
     * Lève une InvalidArgumentException avec un message clair en cas d'erreur.
     */
    private function validateAndCast(string $fieldName, mixed $value, \ReflectionType $type): mixed
    {
        // Si le type est une union (ex: int|string), on laisse passer sans validation stricte
        if ($type instanceof \ReflectionUnionType) {
            // On pourrait valider chaque type, mais pour l'instant on retourne la valeur brute
            return $value;
        }

        if ($type instanceof \ReflectionNamedType) {
            return $this->validateNamedType($fieldName, $value, $type);
        }

        // Autres types exotiques (intersection, etc.) : on ne valide pas
        return $value;
    }

    private function validateNamedType(string $fieldName, mixed $value, \ReflectionNamedType $type): mixed
    {
        $typeName = $type->getName();
        $allowsNull = $type->allowsNull();

        // Gestion du null
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
                // Conversion depuis une chaîne si possible
                if (is_string($value) || is_float($value)) {
                    $intValue = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
                    if ($intValue !== null) {
                        return $intValue;
                    }
                }
                throw new \InvalidArgumentException("Le champ '$fieldName' doit être un entier valide.");

            case 'float':
                if (is_float($value) || is_int($value)) {
                    return (float)$value;
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
                // Types complexes (objets, tableaux) : on pourrait instancier un autre BaseModel, etc.
                // Pour l'instant on laisse la valeur telle quelle.
                return $value;
        }
    }

    private function castToBool(string $fieldName, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) && ($value === 0 || $value === 1)) {
            return (bool)$value;
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

        throw new \InvalidArgumentException("Le champ '$fieldName' doit être un booléen (true/false, 1/0, 'true'/'false', etc.).");
    }
}