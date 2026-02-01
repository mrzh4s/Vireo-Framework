<?php

namespace Framework\Validation\Rules;

/**
 * Same Rule - Two fields must match
 */
class Same implements Rule
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        $otherField = $parameters[0] ?? null;

        if ($otherField === null) {
            return false;
        }

        $otherValue = $data[$otherField] ?? null;

        return $value === $otherValue;
    }

    public function message(string $field, array $parameters): string
    {
        $otherField = $parameters[0] ?? 'other field';
        return "The {$field} must match {$otherField}.";
    }
}
