<?php

namespace Vireo\Framework\Validation\Rules;

/**
 * Rule Interface - Contract for all validation rules
 *
 * All validation rules must implement this interface to ensure
 * consistent behavior across the validation system.
 */
interface Rule
{
    /**
     * Determine if the validation rule passes
     *
     * @param string $field The field name being validated
     * @param mixed $value The value to validate
     * @param array $parameters Rule parameters (e.g., ['255'] for 'max:255')
     * @param array $data All data being validated (for rules that need context)
     * @return bool True if validation passes, false otherwise
     */
    public function passes(string $field, mixed $value, array $parameters, array $data): bool;

    /**
     * Get the validation error message
     *
     * @param string $field The field name
     * @param array $parameters Rule parameters
     * @return string The error message
     */
    public function message(string $field, array $parameters): string;
}
