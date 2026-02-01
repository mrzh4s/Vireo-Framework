<?php

namespace Framework\Validation\Rules;

use DateTime;

/**
 * Date Rule - Value must be a valid date
 */
class Date implements Rule
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Let 'required' rule handle empty values
        }

        if ($value instanceof DateTime) {
            return true;
        }

        $format = $parameters[0] ?? null;

        if ($format !== null) {
            // Validate against specific format
            $date = DateTime::createFromFormat($format, $value);
            return $date && $date->format($format) === $value;
        }

        // Try to parse as any valid date format
        return strtotime($value) !== false;
    }

    public function message(string $field, array $parameters): string
    {
        return "The {$field} is not a valid date.";
    }
}
