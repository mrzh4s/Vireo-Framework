<?php

namespace Vireo\Framework\Validation\Rules;

/**
 * Regex Rule - Value must match a regex pattern
 */
class Regex implements Rule
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Let 'required' rule handle empty values
        }

        $pattern = $parameters[0] ?? null;

        if ($pattern === null) {
            return false;
        }

        return is_string($value) && preg_match($pattern, $value) === 1;
    }

    public function message(string $field, array $parameters): string
    {
        return "The {$field} format is invalid.";
    }
}
