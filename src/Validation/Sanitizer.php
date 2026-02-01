<?php

namespace Framework\Validation;

/**
 * Sanitizer - Input sanitization service
 *
 * Singleton service for sanitizing user input to prevent XSS and other attacks.
 * Follows Pop Framework's singleton pattern.
 */
class Sanitizer
{
    /**
     * @var Sanitizer|null Singleton instance
     */
    private static ?Sanitizer $instance = null;

    /**
     * @var array Configuration
     */
    private array $config = [];

    /**
     * Get singleton instance
     *
     * @return Sanitizer
     */
    public static function getInstance(): Sanitizer
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
        $this->loadConfiguration();
    }

    /**
     * Load sanitization configuration
     *
     * @return void
     */
    private function loadConfiguration(): void
    {
        $configPath = defined('ROOT_PATH') ? ROOT_PATH . '/Config/Validation.php' : __DIR__ . '/../../Config/Validation.php';

        if (file_exists($configPath)) {
            $config = require $configPath;
            $this->config = $config['sanitize'] ?? [];
        } else {
            $this->config = [
                'default_type' => 'string',
                'options' => [
                    'trim' => true,
                    'strip_tags' => false,
                    'escape_html' => true,
                ],
            ];
        }
    }

    /**
     * Sanitize data with optional rules
     *
     * @param mixed $data Data to sanitize
     * @param array|string $rules Sanitization rules (optional)
     * @return mixed Sanitized data
     */
    public function sanitize(mixed $data, array|string $rules = []): mixed
    {
        // If no rules provided, use default type
        if (empty($rules)) {
            $type = $this->config['default_type'] ?? 'string';
            return $this->clean($data, $type);
        }

        // If rules is a string, it's the type
        if (is_string($rules)) {
            return $this->clean($data, $rules);
        }

        // If data is an array, sanitize each element
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                $rule = $rules[$key] ?? ($rules['*'] ?? 'string');
                $sanitized[$key] = $this->clean($value, $rule);
            }
            return $sanitized;
        }

        return $this->clean($data, 'string');
    }

    /**
     * Clean a single value
     *
     * @param mixed $value Value to clean
     * @param string $type Sanitization type
     * @return mixed Cleaned value
     */
    public function clean(mixed $value, string $type = 'string'): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'string' => $this->string($value),
            'email' => $this->email($value),
            'url' => $this->url($value),
            'int', 'integer' => $this->int($value),
            'float' => $this->float($value),
            'bool', 'boolean' => $this->boolean($value),
            'array' => $this->array($value),
            'html' => $this->escapeHtml($value),
            'raw' => $value, // No sanitization
            default => $this->string($value),
        };
    }

    /**
     * Sanitize string
     *
     * @param mixed $value Value to sanitize
     * @return string
     */
    public function string(mixed $value): string
    {
        if (!is_string($value)) {
            $value = (string) $value;
        }

        // Trim whitespace
        if ($this->config['options']['trim'] ?? true) {
            $value = trim($value);
        }

        // Strip tags if configured
        if ($this->config['options']['strip_tags'] ?? false) {
            $value = strip_tags($value);
        }

        // Escape HTML if configured
        if ($this->config['options']['escape_html'] ?? true) {
            $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $value;
    }

    /**
     * Sanitize email address
     *
     * @param mixed $value Value to sanitize
     * @return string
     */
    public function email(mixed $value): string
    {
        if (!is_string($value)) {
            $value = (string) $value;
        }

        $value = trim($value);
        return filter_var($value, FILTER_SANITIZE_EMAIL) ?: '';
    }

    /**
     * Sanitize URL
     *
     * @param mixed $value Value to sanitize
     * @return string
     */
    public function url(mixed $value): string
    {
        if (!is_string($value)) {
            $value = (string) $value;
        }

        $value = trim($value);
        return filter_var($value, FILTER_SANITIZE_URL) ?: '';
    }

    /**
     * Sanitize integer
     *
     * @param mixed $value Value to sanitize
     * @return int
     */
    public function int(mixed $value): int
    {
        return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Sanitize float
     *
     * @param mixed $value Value to sanitize
     * @return float
     */
    public function float(mixed $value): float
    {
        return (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    /**
     * Sanitize boolean
     *
     * @param mixed $value Value to sanitize
     * @return bool
     */
    public function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    /**
     * Sanitize array (recursive)
     *
     * @param mixed $value Value to sanitize
     * @param string $elementType Type for array elements
     * @return array
     */
    public function array(mixed $value, string $elementType = 'string'): array
    {
        if (!is_array($value)) {
            return [];
        }

        $sanitized = [];
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $sanitized[$key] = $this->array($item, $elementType);
            } else {
                $sanitized[$key] = $this->clean($item, $elementType);
            }
        }

        return $sanitized;
    }

    /**
     * Escape HTML special characters
     *
     * @param mixed $value Value to escape
     * @return string
     */
    public function escapeHtml(mixed $value): string
    {
        if (!is_string($value)) {
            $value = (string) $value;
        }

        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Strip HTML and PHP tags
     *
     * @param mixed $value Value to strip
     * @param string $allowedTags Allowed tags (e.g., '<p><a>')
     * @return string
     */
    public function stripTags(mixed $value, string $allowedTags = ''): string
    {
        if (!is_string($value)) {
            $value = (string) $value;
        }

        return strip_tags($value, $allowedTags);
    }

    /**
     * Remove all whitespace
     *
     * @param mixed $value Value to process
     * @return string
     */
    public function removeWhitespace(mixed $value): string
    {
        if (!is_string($value)) {
            $value = (string) $value;
        }

        return preg_replace('/\s+/', '', $value);
    }

    /**
     * Sanitize filename (for file uploads)
     *
     * @param string $filename Filename to sanitize
     * @return string Safe filename
     */
    public function filename(string $filename): string
    {
        // Get file extension
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);

        // Remove special characters
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);

        // Remove multiple underscores
        $basename = preg_replace('/_+/', '_', $basename);

        // Trim underscores and dashes
        $basename = trim($basename, '_-');

        // Ensure not empty
        if (empty($basename)) {
            $basename = 'file_' . time();
        }

        return $extension ? $basename . '.' . $extension : $basename;
    }

    /**
     * Sanitize for SQL LIKE clause (escape wildcards)
     *
     * @param string $value Value to sanitize
     * @return string
     */
    public function likeSafe(string $value): string
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], $value);
    }

    /**
     * Remove invisible characters
     *
     * @param string $value Value to clean
     * @return string
     */
    public function removeInvisibleCharacters(string $value): string
    {
        // Remove null bytes and other invisible characters
        return preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
    }

    /**
     * Sanitize for JSON output
     *
     * @param mixed $value Value to sanitize
     * @return mixed
     */
    public function json(mixed $value): mixed
    {
        if (is_string($value)) {
            // Remove control characters that could break JSON
            $value = $this->removeInvisibleCharacters($value);
        } elseif (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->json($item);
            }
        }

        return $value;
    }

    /**
     * Deep sanitize array (sanitize all nested values)
     *
     * @param array $data Data to sanitize
     * @param string $type Sanitization type
     * @return array
     */
    public function deepSanitize(array $data, string $type = 'string'): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->deepSanitize($value, $type);
            } else {
                $sanitized[$key] = $this->clean($value, $type);
            }
        }

        return $sanitized;
    }
}
