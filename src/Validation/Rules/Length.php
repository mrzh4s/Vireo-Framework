<?php

namespace Framework\Validation\Rules;

/**
 * Length Rule - Value must be exactly a specific length
 */
class Length implements Rule
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Let 'required' rule handle empty values
        }

        $length = $parameters[0] ?? 0;

        if (is_string($value)) {
            return strlen($value) === (int) $length;
        }

        if (is_array($value)) {
            return count($value) === (int) $length;
        }

        return false;
    }

    public function message(string $field, array $parameters): string
    {
        $length = $parameters[0] ?? 0;
        return "The {$field} must be exactly {$length} characters.";
    }
}
