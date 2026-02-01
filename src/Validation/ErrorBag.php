<?php

namespace Vireo\Framework\Validation;

/**
 * ErrorBag - Collects and manages validation errors
 *
 * Simple array-based storage for field-keyed validation errors.
 * Supports multiple errors per field.
 */
class ErrorBag
{
    /**
     * @var array<string, array<string>> Field-keyed error messages
     */
    private array $errors = [];

    /**
     * Add an error message for a field
     *
     * @param string $field Field name
     * @param string $message Error message
     * @return void
     */
    public function add(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $this->errors[$field][] = $message;
    }

    /**
     * Check if a field has errors
     *
     * @param string $field Field name
     * @return bool
     */
    public function has(string $field): bool
    {
        return isset($this->errors[$field]) && !empty($this->errors[$field]);
    }

    /**
     * Get the first error message for a field
     *
     * @param string $field Field name
     * @return string|null
     */
    public function first(string $field): ?string
    {
        if (!$this->has($field)) {
            return null;
        }

        return $this->errors[$field][0];
    }

    /**
     * Get all error messages for a field
     *
     * @param string $field Field name
     * @return array<string>
     */
    public function get(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Get all errors
     *
     * @return array<string, array<string>>
     */
    public function all(): array
    {
        return $this->errors;
    }

    /**
     * Check if there are any errors
     *
     * @return bool
     */
    public function any(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get the total number of errors across all fields
     *
     * @return int
     */
    public function count(): int
    {
        return array_sum(array_map('count', $this->errors));
    }

    /**
     * Check if the error bag is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->errors);
    }

    /**
     * Convert errors to array format
     *
     * @return array<string, array<string>>
     */
    public function toArray(): array
    {
        return $this->errors;
    }

    /**
     * Convert errors to JSON format
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->errors, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Clear all errors
     *
     * @return void
     */
    public function clear(): void
    {
        $this->errors = [];
    }

    /**
     * Clear errors for a specific field
     *
     * @param string $field Field name
     * @return void
     */
    public function clearField(string $field): void
    {
        unset($this->errors[$field]);
    }
}
