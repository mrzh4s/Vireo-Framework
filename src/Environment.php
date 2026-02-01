<?php
/**
 * Environment Variables Handler
 * File: apps/core/Environment.php
 * 
 * Handles loading and parsing of .env files and environment variables
 */

namespace Framework;

class Environment {
    private static $instance = null;
    private $variables = [];
    private $loaded = false;
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Load environment variables from .env file
     */
    public function load($envFile = null) {
        if ($this->loaded) {
            return $this->variables;
        }
        
        $envFile = $envFile ?: ROOT_PATH . '/.env';
        
        if (!file_exists($envFile)) {
            $this->loaded = true;
            return $this->variables;
        }
        
        $content = file_get_contents($envFile);
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $this->parseLine($line);
        }
        
        $this->loaded = true;
        return $this->variables;
    }
    
    /**
     * Parse a single line from .env file
     */
    private function parseLine($line) {
        $line = trim($line);
        
        // Skip empty lines and comments
        if (empty($line) || strpos($line, '#') === 0) {
            return;
        }
        
        // Find the first = sign
        $equalsPos = strpos($line, '=');
        if ($equalsPos === false) {
            return;
        }
        
        $key = trim(substr($line, 0, $equalsPos));
        $value = trim(substr($line, $equalsPos + 1));
        
        // Remove surrounding quotes
        if ($this->isQuoted($value)) {
            $value = substr($value, 1, -1);
        }
        
        // Convert value to appropriate type
        $convertedValue = $this->convertValue($value);
        
        // Store in variables array
        $this->variables[$key] = $convertedValue;
        
        // Set in PHP environment
        $_ENV[$key] = $convertedValue;
        putenv("$key=" . (is_bool($convertedValue) ? ($convertedValue ? '1' : '0') : $convertedValue));
    }
    
    /**
     * Check if value is quoted
     */
    private function isQuoted($value) {
        return (substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
               (substr($value, 0, 1) === "'" && substr($value, -1) === "'");
    }
    
    /**
     * Convert string values to appropriate types
     */
    private function convertValue($value) {
        // Handle boolean values
        $lower = strtolower(trim($value));
        if (in_array($lower, ['true', '1', 'yes', 'on'])) {
            return true;
        }
        if (in_array($lower, ['false', '0', 'no', 'off'])) {
            return false;
        }
        
        // Handle null values
        if (in_array($lower, ['null', 'nil', ''])) {
            return null;
        }
        
        // Handle numeric values
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float)$value : (int)$value;
        }
        
        return $value;
    }
    
    /**
     * Get environment variable
     */
    public function get($key, $default = null) {
        if (!$this->loaded) {
            $this->load();
        }
        
        if (isset($this->variables[$key])) {
            return $this->variables[$key];
        }
        
        // Fallback to PHP environment
        $value = $_ENV[$key] ?? getenv($key);
        if ($value !== false) {
            return $this->convertValue($value);
        }
        
        return $default;
    }
    
    /**
     * Set environment variable
     */
    public function set($key, $value) {
        $this->variables[$key] = $value;
        $_ENV[$key] = $value;
        putenv("$key=" . (is_bool($value) ? ($value ? '1' : '0') : $value));
    }
    
    /**
     * Check if environment variable exists
     */
    public function has($key) {
        return isset($this->variables[$key]) || isset($_ENV[$key]) || getenv($key) !== false;
    }
    
    /**
     * Get all environment variables
     */
    public function all() {
        if (!$this->loaded) {
            $this->load();
        }
        return $this->variables;
    }
    
    /**
     * Check if running in specific environment
     */
    public function is($environment) {
        $current = $this->get('APP_ENV', $this->get('APP_STATE', 'production'));
        return strtolower($current) === strtolower($environment);
    }
    
    /**
     * Check if in debug mode
     */
    public function isDebug() {
        return $this->get('APP_DEBUG', false) || $this->is('local') || $this->is('development');
    }
    
    /**
     * Get environment info for debugging
     */
    public function getDebugInfo() {
        return [
            'loaded' => $this->loaded,
            'file_exists' => file_exists(ROOT_PATH . '/.env'),
            'variables_count' => count($this->variables),
            'environment' => $this->get('APP_ENV', 'unknown'),
            'debug' => $this->isDebug()
        ];
    }
}

