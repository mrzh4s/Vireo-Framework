<?php

namespace Vireo\Framework\Validation\Rules;

/**
 * Numeric Rule - Value must be numeric
 */
class Numeric implements Rule
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Let 'required' rule handle empty values
        }

        return is_numeric($value);
    }

    public function message(string $field, array $parameters): string
    {
        return "The {$field} must be a number.";
    }
}
