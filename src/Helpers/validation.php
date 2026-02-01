<?php

use Vireo\Framework\Validation\Validator;
use Vireo\Framework\Validation\ValidationException;
use Vireo\Framework\Validation\ErrorBag;
use Vireo\Framework\Validation\FileValidator;
use Vireo\Framework\Validation\RateLimiter;
use Vireo\Framework\Validation\Sanitizer;

/**
 * Validation Helper Functions
 *
 * Global helper functions for easy access to validation functionality.
 * Auto-loaded by Bootstrap following the framework's helper pattern.
 */

if (!function_exists('validator')) {
    /**
     * Get validator instance or create new validator with data
     *
     * Usage:
     *   $validator = validator($data, $rules);
     *   $validator = validator(); // Get singleton instance
     *
     * @param array $data Data to validate (optional)
     * @param array $rules Validation rules (optional)
     * @param array $messages Custom error messages (optional)
     * @return Validator
     */
    function validator(array $data = [], array $rules = [], array $messages = []): Validator
    {
        if (empty($data)) {
            return Validator::getInstance();
        }

        return Validator::getInstance()->make($data, $rules, $messages);
    }
}

if (!function_exists('validate')) {
    /**
     * Validate data and throw exception on failure
     *
     * Usage:
     *   $validated = validate($data, ['email' => 'required|email']);
     *
     * @param array $data Data to validate
     * @param array $rules Validation rules
     * @param array $messages Custom error messages (optional)
     * @return array Validated data
     * @throws ValidationException When validation fails
     */
    function validate(array $data, array $rules, array $messages = []): array
    {
        return Validator::getInstance()->validate($data, $rules, $messages);
    }
}

if (!function_exists('validation_errors')) {
    /**
     * Get validation errors from the last validation
     *
     * Usage:
     *   $errors = validation_errors(); // Get all errors
     *   $emailErrors = validation_errors('email'); // Get errors for specific field
     *
     * @param string|null $field Field name (optional)
     * @return array|ErrorBag Array of errors for field, or ErrorBag for all errors
     */
    function validation_errors(?string $field = null): array|ErrorBag
    {
        $validator = Validator::getInstance();
        $errors = $validator->errors();

        if ($field !== null) {
            return $errors->get($field);
        }

        return $errors;
    }
}

if (!function_exists('add_validator')) {
    /**
     * Register a custom validation rule
     *
     * Usage:
     *   add_validator('uppercase', function($field, $value) {
     *       return $value === strtoupper($value);
     *   }, 'The :field must be uppercase.');
     *
     * @param string $name Rule name
     * @param callable $callback Validation callback
     * @param string|null $message Custom error message (optional)
     * @return void
     */
    function add_validator(string $name, callable $callback, ?string $message = null): void
    {
        Validator::getInstance()->extend($name, $callback, $message);
    }
}

if (!function_exists('validate_required')) {
    /**
     * Backward compatible simple required field validation
     * (Maintains compatibility with existing Request::validate())
     *
     * @param array $fields Fields to check
     * @param array $data Data to validate against (optional, uses request data if not provided)
     * @return array Missing fields
     */
    function validate_required(array $fields, array $data = []): array
    {
        if (empty($data)) {
            $data = request()->all();
        }

        $missing = [];
        foreach ($fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missing[] = $field;
            }
        }

        return $missing;
    }
}

if (!function_exists('file_validator')) {
    /**
     * Get file validator instance
     *
     * Usage:
     *   $validator = file_validator();
     *   $validator->validate($_FILES['avatar'], ['mimes' => ['jpeg', 'png'], 'maxSize' => 2048]);
     *
     * @return FileValidator
     */
    function file_validator(): FileValidator
    {
        return FileValidator::getInstance();
    }
}

if (!function_exists('rate_limiter')) {
    /**
     * Get rate limiter instance
     *
     * Usage:
     *   $limiter = rate_limiter();
     *   if (!$limiter->attempt('login_' . $ip, 5, 15)) {
     *       // Too many attempts
     *   }
     *
     * @return RateLimiter
     */
    function rate_limiter(): RateLimiter
    {
        return RateLimiter::getInstance();
    }
}

if (!function_exists('throttle')) {
    /**
     * Check rate limit (quick helper)
     *
     * Usage:
     *   if (!throttle('api_search', 30, 1)) {
     *       return Response::error('Too many requests', 429);
     *   }
     *
     * @param string $key Unique identifier for the rate limit
     * @param int $maxAttempts Maximum number of attempts
     * @param int $decayMinutes Time window in minutes
     * @return bool True if allowed, false if rate limited
     */
    function throttle(string $key, int $maxAttempts, int $decayMinutes = 1): bool
    {
        return RateLimiter::getInstance()->attempt($key, $maxAttempts, $decayMinutes);
    }
}

if (!function_exists('sanitize')) {
    /**
     * Sanitize data
     *
     * Usage:
     *   $clean = sanitize($data); // Default string sanitization
     *   $clean = sanitize($data, 'email'); // Email sanitization
     *   $clean = sanitize(['name' => 'John'], ['name' => 'string']); // With rules
     *
     * @param mixed $data Data to sanitize
     * @param array|string $rules Sanitization rules (optional)
     * @return mixed Sanitized data
     */
    function sanitize(mixed $data, array|string $rules = []): mixed
    {
        return Sanitizer::getInstance()->sanitize($data, $rules);
    }
}
