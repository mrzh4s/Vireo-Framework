<?php

namespace Framework\Validation;

use Framework\Validation\Rules\Rule;
use Exception;

/**
 * Validator - Core validation engine
 *
 * Singleton service that handles data validation against rules.
 * Supports both string ('required|email|max:255') and array formats.
 * Follows Pop Framework's singleton pattern like CSRFToken, Permission, Cookie classes.
 */
class Validator
{
    /**
     * @var Validator|null Singleton instance
     */
    private static ?Validator $instance = null;

    /**
     * @var array<string, Rule> Registered validation rules
     */
    private array $rules = [];

    /**
     * @var array<string, callable> Custom validation rules
     */
    private array $customRules = [];

    /**
     * @var array<string, string> Default validation messages
     */
    private array $messages = [];

    /**
     * @var ErrorBag Current validation errors
     */
    private ErrorBag $errors;

    /**
     * @var array Data being validated
     */
    private array $data = [];

    /**
     * @var array Custom error messages for this validation
     */
    private array $customMessages = [];

    /**
     * @var bool Whether validation has been performed
     */
    private bool $validated = false;

    /**
     * Get singleton instance
     *
     * @return Validator
     */
    public static function getInstance(): Validator
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Private constructor - Singleton pattern
     */
    private function __construct()
    {
        $this->errors = new ErrorBag();
        $this->loadConfiguration();
        $this->registerBuiltInRules();
    }

    /**
     * Load validation configuration
     *
     * @return void
     */
    private function loadConfiguration(): void
    {
        $configPath = defined('ROOT_PATH') ? ROOT_PATH . '/Config/Validation.php' : __DIR__ . '/../../Config/Validation.php';

        if (file_exists($configPath)) {
            $config = require $configPath;
            $this->messages = $config['messages'] ?? [];
        } else {
            $this->messages = $this->getDefaultMessages();
        }
    }

    /**
     * Get default validation messages
     *
     * @return array<string, string>
     */
    private function getDefaultMessages(): array
    {
        return [
            'required' => 'The :field field is required.',
            'email' => 'The :field must be a valid email address.',
            'numeric' => 'The :field must be a number.',
            'integer' => 'The :field must be an integer.',
            'string' => 'The :field must be a string.',
            'min' => 'The :field must be at least :min.',
            'max' => 'The :field may not be greater than :max.',
            'url' => 'The :field must be a valid URL.',
            'alpha' => 'The :field may only contain letters.',
            'alpha_numeric' => 'The :field may only contain letters and numbers.',
            'length' => 'The :field must be exactly :length characters.',
            'regex' => 'The :field format is invalid.',
            'same' => 'The :field must match :other.',
            'different' => 'The :field must be different from :other.',
            'confirmed' => 'The :field confirmation does not match.',
            'in' => 'The selected :field is invalid.',
            'not_in' => 'The selected :field is invalid.',
            'date' => 'The :field is not a valid date.',
            'before_date' => 'The :field must be a date before :date.',
            'after_date' => 'The :field must be a date after :date.',
            'unique' => 'The :field has already been taken.',
            'exists' => 'The selected :field is invalid.',
            'file' => 'The :field must be a file.',
            'mimes' => 'The :field must be a file of type: :mimes.',
        ];
    }

    /**
     * Register built-in validation rules
     *
     * @return void
     */
    private function registerBuiltInRules(): void
    {
        // Rules will be loaded lazily when needed
        // This prevents loading all rule classes unnecessarily
    }

    /**
     * Create a new validator instance (fluent interface)
     *
     * @param array $data Data to validate
     * @param array $rules Validation rules
     * @param array $messages Custom error messages
     * @return static
     */
    public function make(array $data, array $rules, array $messages = []): static
    {
        // Create a new instance for this validation
        $validator = new static();
        $validator->data = $data;
        $validator->customMessages = $messages;
        $validator->errors = new ErrorBag();
        $validator->validated = false;

        // Perform validation
        $validator->performValidation($rules);

        return $validator;
    }

    /**
     * Validate data and throw exception on failure
     *
     * @param array $data Data to validate
     * @param array $rules Validation rules
     * @param array $messages Custom error messages
     * @return array Validated data
     * @throws ValidationException
     */
    public function validate(array $data, array $rules, array $messages = []): array
    {
        $validator = $this->make($data, $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator->errors(), 'Validation failed', $data);
        }

        // Return only the validated fields
        return $validator->getValidated();
    }

    /**
     * Perform the actual validation
     *
     * @param array $rules Validation rules
     * @return void
     */
    private function performValidation(array $rules): void
    {
        foreach ($rules as $field => $fieldRules) {
            $this->validateField($field, $fieldRules);
        }

        $this->validated = true;
    }

    /**
     * Validate a single field
     *
     * @param string $field Field name (supports dot notation)
     * @param mixed $fieldRules Validation rules (string or array)
     * @return void
     */
    private function validateField(string $field, mixed $fieldRules): void
    {
        // Parse rules
        $rules = $this->parseRules($fieldRules);

        // Get value using dot notation
        $value = $this->getValue($field);

        // Execute each rule
        foreach ($rules as $ruleName => $parameters) {
            $this->executeRule($field, $value, $ruleName, $parameters);
        }
    }

    /**
     * Parse validation rules
     *
     * @param mixed $fieldRules Validation rules (string or array)
     * @return array<string, array> Parsed rules with parameters
     */
    private function parseRules(mixed $fieldRules): array
    {
        if (is_string($fieldRules)) {
            return $this->parseRuleString($fieldRules);
        }

        if (is_array($fieldRules)) {
            $parsed = [];
            foreach ($fieldRules as $key => $value) {
                if (is_int($key)) {
                    // Array format: ['required', 'email']
                    $rule = $this->parseRuleString($value);
                    $parsed = array_merge($parsed, $rule);
                } else {
                    // Associative format: ['max' => 255]
                    $parsed[$key] = is_array($value) ? $value : [$value];
                }
            }
            return $parsed;
        }

        return [];
    }

    /**
     * Parse a rule string like 'required|email|max:255'
     *
     * @param string $ruleString Rule string
     * @return array<string, array> Parsed rules
     */
    private function parseRuleString(string $ruleString): array
    {
        $rules = [];
        $parts = explode('|', $ruleString);

        foreach ($parts as $part) {
            if (str_contains($part, ':')) {
                [$name, $params] = explode(':', $part, 2);
                $rules[$name] = explode(',', $params);
            } else {
                $rules[$part] = [];
            }
        }

        return $rules;
    }

    /**
     * Get value from data using dot notation
     *
     * @param string $field Field name (supports dot notation like 'user.email')
     * @return mixed
     */
    private function getValue(string $field): mixed
    {
        if (isset($this->data[$field])) {
            return $this->data[$field];
        }

        // Handle dot notation
        if (str_contains($field, '.')) {
            $keys = explode('.', $field);
            $value = $this->data;

            foreach ($keys as $key) {
                if (is_array($value) && isset($value[$key])) {
                    $value = $value[$key];
                } else {
                    return null;
                }
            }

            return $value;
        }

        return null;
    }

    /**
     * Execute a validation rule
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $ruleName Rule name
     * @param array $parameters Rule parameters
     * @return void
     */
    private function executeRule(string $field, mixed $value, string $ruleName, array $parameters): void
    {
        // Check if it's a custom rule
        if (isset($this->customRules[$ruleName])) {
            $passes = call_user_func($this->customRules[$ruleName]['callback'], $field, $value, $parameters, $this->data);

            if (!$passes) {
                $message = $this->customRules[$ruleName]['message'] ?? "The {$field} is invalid.";
                $this->errors->add($field, $this->formatMessage($message, $field, $parameters));
            }

            return;
        }

        // Load rule class
        $ruleClass = $this->getRuleClass($ruleName);

        if ($ruleClass === null) {
            // Rule not found, skip silently or log error
            return;
        }

        $rule = new $ruleClass();

        if (!$rule->passes($field, $value, $parameters, $this->data)) {
            $message = $rule->message($field, $parameters);
            $this->errors->add($field, $this->formatMessage($message, $field, $parameters));
        }
    }

    /**
     * Get rule class name
     *
     * @param string $ruleName Rule name
     * @return string|null
     */
    private function getRuleClass(string $ruleName): ?string
    {
        // Map reserved PHP keywords to safe class names
        $reservedKeywordMap = [
            'string' => 'StringRule',
            'int' => 'IntRule',
            'float' => 'FloatRule',
            'bool' => 'BoolRule',
            'object' => 'ObjectRule',
            'array' => 'ArrayRule',
        ];

        // Check if rule name is a reserved keyword
        if (isset($reservedKeywordMap[$ruleName])) {
            $className = $reservedKeywordMap[$ruleName];
        } else {
            // Convert rule name to class name (snake_case to PascalCase)
            $className = str_replace('_', '', ucwords($ruleName, '_'));
        }

        $fullClassName = "Framework\\Validation\\Rules\\{$className}";

        if (class_exists($fullClassName)) {
            return $fullClassName;
        }

        return null;
    }

    /**
     * Format error message with placeholders
     *
     * @param string $message Message template
     * @param string $field Field name
     * @param array $parameters Rule parameters
     * @return string
     */
    private function formatMessage(string $message, string $field, array $parameters): string
    {
        // Replace :field placeholder
        $message = str_replace(':field', $field, $message);

        // Replace parameter placeholders
        foreach ($parameters as $index => $param) {
            $message = str_replace(":{$index}", $param, $message);
        }

        // Replace named parameter placeholders
        if (!empty($parameters)) {
            $message = str_replace(':min', $parameters[0] ?? '', $message);
            $message = str_replace(':max', $parameters[0] ?? '', $message);
            $message = str_replace(':length', $parameters[0] ?? '', $message);
            $message = str_replace(':other', $parameters[0] ?? '', $message);
            $message = str_replace(':date', $parameters[0] ?? '', $message);
            $message = str_replace(':mimes', implode(', ', $parameters), $message);
        }

        return $message;
    }

    /**
     * Register a custom validation rule
     *
     * @param string $name Rule name
     * @param callable $callback Validation callback (field, value, parameters, data) => bool
     * @param string|null $message Custom error message
     * @return void
     */
    public function extend(string $name, callable $callback, ?string $message = null): void
    {
        $this->customRules[$name] = [
            'callback' => $callback,
            'message' => $message ?? "The {$name} validation failed.",
        ];
    }

    /**
     * Check if validation passed
     *
     * @return bool
     */
    public function passes(): bool
    {
        return $this->errors->isEmpty();
    }

    /**
     * Check if validation failed
     *
     * @return bool
     */
    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * Get validation errors
     *
     * @return ErrorBag
     */
    public function errors(): ErrorBag
    {
        return $this->errors;
    }

    /**
     * Get validated data (only fields that were validated)
     *
     * @return array
     */
    public function getValidated(): array
    {
        // For now, return all data
        // In a more advanced implementation, we'd track which fields were validated
        return $this->data;
    }

    /**
     * Reset validator state (for reuse)
     *
     * @return void
     */
    public function reset(): void
    {
        $this->errors->clear();
        $this->data = [];
        $this->customMessages = [];
        $this->validated = false;
    }
}
