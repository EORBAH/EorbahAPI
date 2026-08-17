<?php

namespace Eorbahapi\Validator;

class Field {
    private bool $required = false;
    private bool $optional = false;
    private ?string $alias = null;
    private mixed $defaultValue = null;
    private array $rules = [];
    private array $messages = [];

    public static function required(): self
    {
        $field = new self();
        $field->required = true;
        return $field;
    }

    public static function optional(): self
    {
        $field = new self();
        $field->optional = true;
        return $field;
    }

    public function alias(string $name): self
    {
        $this->alias = $name;
        return $this;
    }

    public function defaultValue(mixed $value): self
    {
        $this->defaultValue = $value;
        return $this;
    }

    public function min(int $min): self
    {
        $this->rules[] = fn($value) => is_numeric($value) && $value >= $min
            ? true
            : ['min' => "La valeur doit être supérieure ou égale à {$min}."];
        return $this;
    }

    public function max(int $max): self
    {
        $this->rules[] = fn($value) => is_numeric($value) && $value <= $max
            ? true
            : ['max' => "La valeur doit être inférieure ou égale à {$max}."];
        return $this;
    }

    public function minLength(int $min): self
    {
        $this->rules[] = fn($value) => is_string($value) && mb_strlen($value) >= $min
            ? true
            : ['minLength' => "La chaîne doit contenir au moins {$min} caractères."];
        return $this;
    }

    public function maxLength(int $max): self
    {
        $this->rules[] = fn($value) => is_string($value) && mb_strlen($value) <= $max
            ? true
            : ['maxLength' => "La chaîne ne doit pas dépasser {$max} caractères."];
        return $this;
    }

    public function regex(string $pattern): self
    {
        $this->rules[] = fn($value) => is_string($value) && preg_match($pattern, $value)
            ? true
            : ['regex' => 'La valeur ne correspond pas au format attendu.'];
        return $this;
    }

    public function email(): self
    {
        $this->rules[] = fn($value) => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false
            ? true
            : ['email' => 'L’adresse email est invalide.'];
        return $this;
    }

    public function oneOf(array $values): self
    {
        $this->rules[] = fn($value) => in_array($value, $values, true)
            ? true
            : ['oneOf' => 'La valeur doit être l’une des valeurs autorisées.'];
        return $this;
    }

    public function validate(mixed $value, string $fieldName): mixed
    {
        foreach ($this->rules as $rule) {
            $result = $rule($value);
            if ($result !== true) {
                $message = is_array($result) ? reset($result) : 'La valeur du champ est invalide.';
                throw new \InvalidArgumentException("Le champ '$fieldName' est invalide : {$message}");
            }
        }

        return $value;
    }

    public function getSourceName(): ?string
    {
        return $this->alias;
    }

    public function isRequired(): bool
    {
        return $this->required || (!$this->optional && $this->defaultValue === null && $this->alias === null);
    }

    public function hasDefaultValue(): bool
    {
        return $this->defaultValue !== null || $this->optional;
    }

    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }
}
