<?php

namespace Vireo\Framework\Validation\Rules;

/**
 * AlphaNumeric Rule - Value may only contain alphanumeric characters
 */
class AlphaNumeric implements Rule
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Let 'required' rule handle empty values
        }

        return is_string($value) && preg_match('/^[a-zA-Z0-9]+$/', $value);
    }

    public function message(string $field, array $parameters): string
    {
        return "The {$field} may only contain letters and numbers.";
    }
}
