<?php

namespace Vireo\Framework\Validation\Rules;

/**
 * Email Rule - Value must be a valid email address
 */
class Email implements Rule
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Let 'required' rule handle empty values
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function message(string $field, array $parameters): string
    {
        return "The {$field} must be a valid email address.";
    }
}
