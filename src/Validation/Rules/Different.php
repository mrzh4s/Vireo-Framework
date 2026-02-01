<?php

namespace Vireo\Framework\Validation\Rules;

/**
 * Different Rule - Two fields must be different
 */
class Different implements Rule
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        $otherField = $parameters[0] ?? null;

        if ($otherField === null) {
            return true;
        }

        $otherValue = $data[$otherField] ?? null;

        return $value !== $otherValue;
    }

    public function message(string $field, array $parameters): string
    {
        $otherField = $parameters[0] ?? 'other field';
        return "The {$field} must be different from {$otherField}.";
    }
}
