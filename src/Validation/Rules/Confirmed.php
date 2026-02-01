<?php

namespace Vireo\Framework\Validation\Rules;

/**
 * Confirmed Rule - Field must have a matching confirmation field
 * Example: 'password' must match 'password_confirmation'
 */
class Confirmed implements Rule
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        $confirmationField = $field . '_confirmation';
        $confirmationValue = $data[$confirmationField] ?? null;

        return $value === $confirmationValue;
    }

    public function message(string $field, array $parameters): string
    {
        return "The {$field} confirmation does not match.";
    }
}
