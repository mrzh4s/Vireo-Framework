<?php
/**
 * Configuration Manager (Enhanced and Fixed)
 * File: apps/core/Configuration.php
 * 
 * Handles application configuration with dot notation support
 * Enhanced with app helper functions to replace System.php
 * Fixed: Removed duplicate env() function to avoid conflicts
 */
namespace Vireo\Framework;
use Vireo\Framework\Environment;
use Vireo\Framework\Http\Session;

class Configuration {
    private static $instance = null;
    private $config = [];
    private $loaded = false;
    
    // Component references to replace System.php functionality
    private $environment;
    private $session;
    private $permission;
    
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
     * Initialize configuration and components
     */
    public function __construct() {
        $this->initializeComponents();
    }
    
    /**
     * Initialize core components (replaces System.php functionality)
     */
    private function initializeComponents() {
        // Load Environment first
        if (class_exists('Environment')) {
            $this->environment = Environment::getInstance();
        }
        
        // Initialize Session if available
        if (class_exists('Session')) {
            $this->session = Session::getInstance();
        }
    }
    
    /**
     * Load configuration from environment variables
     */
    public function load() {
        if ($this->loaded) {
            return $this->config;
        }
        
        // Get environment variables
        $env = $this->environment ?: Environment::getInstance();
        $envVars = $env->all();
        
        // Auto-discover configuration based on prefixes
        $this->autoDiscoverFromEnvironment($envVars);
        
        // Set up predefined configuration objects
        $this->setupPredefinedConfig();
        
        $this->loaded = true;
        return $this->config;
    }
    
    /**
     * Auto-discover configuration from environment variables
     */
    private function autoDiscoverFromEnvironment($envVars) {
        $groups = [];
        
        foreach ($envVars as $key => $value) {
            // Split by underscore to determine grouping
            $parts = explode('_', $key);
            
            if (count($parts) >= 2) {
                $prefix = strtolower($parts[0]);
                $configKey = strtolower(implode('_', array_slice($parts, 1)));
                
                if (!isset($groups[$prefix])) {
                    $groups[$prefix] = [];
                }
                
                $groups[$prefix][$configKey] = $value;
            }
        }
        
        $this->config = $groups;
    }
    
    /**
     * Set up predefined configuration for backward compatibility
     */
    private function setupPredefinedConfig() {
        $env = $this->environment ?: Environment::getInstance();
        
        // App configuration
        $this->config['app'] = array_merge($this->config['app'] ?? [], [
            'name' => $env->get('APP_NAME', 'KITER'),
            'title' => $env->get('APP_TITLE', $env->get('APP_NAME', 'KITER')),
            'version' => $env->get('APP_VERSION', '1.0.0'),
            'environment' => $env->get('APP_ENV', $env->get('APP_STATE', 'production')),
            'debug' => $env->get('APP_DEBUG', false),
            'url' => $this->normalizeUrl($env->get('APP_URL', 'http://localhost')),
            'timezone' => $env->get('APP_TIMEZONE', 'Asia/Kuala_Lumpur'),
            'company' => $env->get('APP_COMPANY', ''),
            'tenant' => $env->get('APP_TENANT', ''),
            'secret_key' => $env->get('APP_SECRET_KEY', $env->get('SECRET_KEY', ''))
        ]);

        // Main configuration
        $this->config['main'] = array_merge($this->config['main'] ?? [], [
            'db' => $env->get('APP_DB', 'database/app.db')
        ]);
        
        // Source Database configuration
        $this->config['source'] = array_merge($this->config['source'] ?? [], [
            'host' => $env->get('SOURCE_DB_HOST', 'localhost'),
            'port' => $env->get('SOURCE_DB_PORT', '5432'),
            'database' => $env->get('SOURCE_DB_DATABASE', 'kiter'),
            'username' => $env->get('SOURCE_DB_USERNAME', 'postgres'),
            'password' => $env->get('SOURCE_DB_PASSWORD', ''),
            'driver' => $env->get('SOURCE_DB_DRIVER', 'pgsql'),
        ]);

        // Destination Database configuration
        $this->config['dest'] = array_merge($this->config['dest'] ?? [], [
            'host' => $env->get('DEST_DB_HOST', 'localhost'),
            'port' => $env->get('DEST_DB_PORT', '5432'),
            'database' => $env->get('DEST_DB_DATABASE', 'kiter'),
            'username' => $env->get('DEST_DB_USERNAME', 'postgres'),
            'password' => $env->get('DEST_DB_PASSWORD', ''),
            'driver' => $env->get('DEST_DB_DRIVER', 'pgsql'),
        ]);
        
        // FTP configuration
        $this->config['ftp'] = array_merge($this->config['ftp'] ?? [], [
            'host' => $env->get('DEST_FTP_HOST', ''),
            'port' => $env->get('DEST_FTP_PORT', '21'),
            'username' => $env->get('DEST_FTP_USERNAME', ''),
            'password' => $env->get('DEST_FTP_PASSWORD', ''),
            'path' => $env->get('DEST_FTP_PATH', '')
        ]);

        // Gravity Forms API configuration
        $this->config['gf'] = array_merge($this->config['gf'] ?? [], [
            'url' => $env->get('GF_API_URL', ''),
            'key' => $env->get('GF_CONSUMER_KEY', ''),
            'secret' => $env->get('GF_CONSUMER_SECRET', '')
        ]);
    }
    
    /**
     * Normalize URL format
     */
    private function normalizeUrl($url) {
        if (empty($url)) {
            return '';
        }
        
        // Ensure URL has protocol
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'http://' . $url;
        }
        
        return rtrim($url, '/');
    }
    
    /**
     * Get config value using dot notation
     */
    public function get($key, $default = null) {
        if (!$this->loaded) {
            $this->load();
        }
        
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
    
    /**
     * Set config value using dot notation
     */
    public function set($key, $value) {
        if (!$this->loaded) {
            $this->load();
        }
        
        $keys = explode('.', $key);
        $config = &$this->config;
        
        while (count($keys) > 1) {
            $segment = array_shift($keys);
            if (!isset($config[$segment]) || !is_array($config[$segment])) {
                $config[$segment] = [];
            }
            $config = &$config[$segment];
        }
        
        $config[array_shift($keys)] = $value;
    }
    
    /**
     * Check if config key exists
     */
    public function has($key) {
        return $this->get($key) !== null;
    }
    
    /**
     * Get all configuration
     */
    public function all() {
        if (!$this->loaded) {
            $this->load();
        }
        return $this->config;
    }
    
    /**
     * Get config group
     */
    public function group($group) {
        return $this->get($group, []);
    }
    
    /**
     * Create configuration objects for backward compatibility
     */
    public function createObjects() {
        if (!$this->loaded) {
            $this->load();
        }
        
        return (object) [
            'App' => (object) $this->group('app'),
            'Main' => (object) $this->group('main'),
            'GF' => (object) $this->group('gf'),
            'Source' => (object) $this->group('source'),
            'Dest' => (object) $this->group('dest'),
            'FTP' => (object) $this->group('ftp'),
        ];
    }
    
    // ============== COMPONENT ACCESS METHODS (replaces System.php) ==============
    
    /**
     * Get environment component
     */
    public function getEnvironment() {
        return $this->environment;
    }
    
    /**
     * Get session component
     */
    public function getSession() {
        return $this->session;
    }
    
    /**
     * Get permission component
     */
    public function getPermission() {
        return $this->permission;
    }
    
    // ============== APP HELPER METHODS (replaces System.php) ==============
    
    /**
     * Get environment variable (proxy to Environment)
     * This is a CLASS METHOD, not a global function
     */
    public function env($key, $default = null) {
        return $this->environment ? $this->environment->get($key, $default) : ($_ENV[$key] ?? $default);
    }
    
    /**
     * Get session value (proxy to Session)
     */
    public function session($key = null, $default = null) {
        if (!$this->session) return $default;
        
        if ($key === null) {
            return $this->session->all();
        }
        return $this->session->get($key, $default);
    }
    
    /**
     * Set session value (proxy to Session)
     */
    public function setSession($key, $value) {
        return $this->session ? $this->session->set($key, $value) : false;
    }
    
    /**
     * Get debug information
     */
    public function getDebugInfo() {
        return [
            'loaded' => $this->loaded,
            'groups' => array_keys($this->config),
            'app_name' => $this->get('app.name'),
            'app_environment' => $this->get('app.environment'),
            'app_debug' => $this->get('app.debug'),
            'app_url' => $this->get('app.url'),
            'components' => [
                'environment' => $this->environment ? 'loaded' : 'not loaded',
                'session' => $this->session ? 'loaded' : 'not loaded', 
            ]
        ];
    }
}

