<?php

namespace Vireo\Framework\Validation\Rules;

/**
 * Alpha Rule - Value may only contain alphabetic characters
 */
class Alpha implements Rule
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Let 'required' rule handle empty values
        }

        return is_string($value) && preg_match('/^[a-zA-Z]+$/', $value);
    }

    public function message(string $field, array $parameters): string
    {
        return "The {$field} may only contain letters.";
    }
}
