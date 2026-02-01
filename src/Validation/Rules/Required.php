<?php

namespace Framework\Validation\Rules;

/**
 * Required Rule - Field must be present and non-empty
 */
class Required implements Rule
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if (is_array($value) && empty($value)) {
            return false;
        }

        return true;
    }

    public function message(string $field, array $parameters): string
    {
        return "The {$field} field is required.";
    }
}
