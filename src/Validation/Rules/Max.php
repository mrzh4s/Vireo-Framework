<?php

namespace Vireo\Framework\Validation\Rules;

/**
 * Max Rule - Value may not exceed a maximum value/length
 */
class Max implements Rule
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Let 'required' rule handle empty values
        }

        $max = $parameters[0] ?? 0;

        // Numeric comparison
        if (is_numeric($value)) {
            return $value <= $max;
        }

        // String length comparison
        if (is_string($value)) {
            return strlen($value) <= $max;
        }

        // Array count comparison
        if (is_array($value)) {
            return count($value) <= $max;
        }

        return false;
    }

    public function message(string $field, array $parameters): string
    {
        $max = $parameters[0] ?? 0;
        return "The {$field} may not be greater than {$max}.";
    }
}
