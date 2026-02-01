<?php

namespace Vireo\Framework\Validation\Rules;

/**
 * String Rule - Value must be a string
 */
class StringRule implements Rule
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Let 'required' rule handle empty values
        }

        return is_string($value);
    }

    public function message(string $field, array $parameters): string
    {
        return "The {$field} must be a string.";
    }
}
