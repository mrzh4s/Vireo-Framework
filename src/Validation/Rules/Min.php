<?php

namespace Vireo\Framework\Validation\Rules;

/**
 * Min Rule - Value must be at least a minimum value/length
 */
class Min implements Rule
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Let 'required' rule handle empty values
        }

        $min = $parameters[0] ?? 0;

        // Numeric comparison
        if (is_numeric($value)) {
            return $value >= $min;
        }

        // String length comparison
        if (is_string($value)) {
            return strlen($value) >= $min;
        }

        // Array count comparison
        if (is_array($value)) {
            return count($value) >= $min;
        }

        return false;
    }

    public function message(string $field, array $parameters): string
    {
        $min = $parameters[0] ?? 0;
        return "The {$field} must be at least {$min}.";
    }
}
