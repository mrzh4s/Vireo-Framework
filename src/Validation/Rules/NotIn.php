<?php

namespace Vireo\Framework\Validation\Rules;

/**
 * NotIn Rule - Value must not be in a given list
 */
class NotIn implements Rule
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Let 'required' rule handle empty values
        }

        return !in_array($value, $parameters, true);
    }

    public function message(string $field, array $parameters): string
    {
        return "The selected {$field} is invalid.";
    }
}
