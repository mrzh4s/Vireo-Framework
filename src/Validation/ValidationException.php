<?php

namespace Framework\Validation;

use Exception;

/**
 * ValidationException - Exception thrown when validation fails
 *
 * Contains an ErrorBag instance with all validation errors.
 * Extends base Exception class for standard exception handling.
 */
class ValidationException extends Exception
{
    /**
     * @var ErrorBag The error bag containing validation errors
     */
    private ErrorBag $errorBag;

    /**
     * @var array The validated data (if any)
     */
    private array $data;

    /**
     * Create a new validation exception
     *
     * @param ErrorBag $errorBag The error bag with validation errors
     * @param string $message Exception message
     * @param array $data The data that failed validation
     */
    public function __construct(ErrorBag $errorBag, string $message = 'Validation failed', array $data = [])
    {
        parent::__construct($message);
        $this->errorBag = $errorBag;
        $this->data = $data;
    }

    /**
     * Get the error bag
     *
     * @return ErrorBag
     */
    public function errors(): ErrorBag
    {
        return $this->errorBag;
    }

    /**
     * Get validation errors as array
     *
     * @return array<string, array<string>>
     */
    public function getErrors(): array
    {
        return $this->errorBag->toArray();
    }

    /**
     * Get the data that failed validation
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get the first error message
     *
     * @return string|null
     */
    public function getFirstError(): ?string
    {
        $all = $this->errorBag->all();
        if (empty($all)) {
            return null;
        }

        $firstField = array_key_first($all);
        return $this->errorBag->first($firstField);
    }

    /**
     * Convert exception to array format for API responses
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'errors' => $this->errorBag->toArray(),
        ];
    }

    /**
     * Convert exception to JSON format
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
