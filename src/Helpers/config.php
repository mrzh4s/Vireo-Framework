<?php

use Vireo\Framework\Configuration;
// ============== CORE CONFIGURATION FUNCTIONS ==============

if (!function_exists('config')) {
    /**
     * Get config value using dot notation
     */
    function config($key, $default = null) {
        return Configuration::getInstance()->get($key, $default);
    }
}

if (!function_exists('config_set')) {
    /**
     * Set config value
     */
    function config_set($key, $value) {
        return Configuration::getInstance()->set($key, $value);
    }
}

if (!function_exists('config_has')) {
    /**
     * Check if config key exists
     */
    function config_has($key) {
        return Configuration::getInstance()->has($key);
    }
}

if (!function_exists('config_all')) {
    /**
     * Get all configuration
     */
    function config_all() {
        return Configuration::getInstance()->all();
    }
}

// ============== APP HELPER FUNCTIONS (replaces System.php) ==============

if (!function_exists('app')) {
    /**
     * Get the Configuration instance or specific config value
     * This replaces System::getInstance()
     */
    function app($key = null, $default = null) {
        $config = Configuration::getInstance();
        
        if ($key === null) {
            return $config;
        }
        
        return $config->get($key, $default);
    }
}

// ============== REMOVED: env() function ==============
// The env() function is now ONLY in Environment.php to avoid conflicts
// Use env() for direct environment access or app()->env() for proxied access

// ============== APP-SPECIFIC CONFIG HELPERS ==============

if (!function_exists('app_name')) {
    function app_name() {
        return config('app.name', 'KITER');
    }
}

if (!function_exists('app_title')) {
    function app_title() {
        return config('app.title', app_name());
    }
}

if (!function_exists('app_version')) {
    function app_version() {
        return config('app.version', '1.0.0');
    }
}

if (!function_exists('app_url')) {
    function app_url($path = '') {
        $baseUrl = config('app.url', 'http://localhost');
        
        if ($path) {
            return $baseUrl . '/' . ltrim($path, '/');
        }
        
        return $baseUrl;
    }
}

if (!function_exists('app_company')) {
    function app_company() {
        return config('app.company', '');
    }
}

if (!function_exists('app_tenant')) {
    function app_tenant() {
        return config('app.tenant', '');
    }
}

if (!function_exists('app_secret')) {
    function app_secret() {
        return config('app.secret_key', '');
    }
}

if (!function_exists('app_env')) {
    function app_env($environment = null) {
        $current = config('app.environment', 'production');
        
        if ($environment === null) {
            return $current;
        }
        
        return strtolower($current) === strtolower($environment);
    }
}

if (!function_exists('is_local')) {
    function is_local() {
        return app_env('local');
    }
}

if (!function_exists('is_production')) {
    function is_production() {
        return app_env('production');
    }
}

if (!function_exists('app_debug')) {
    function app_debug() {
        return config('app.debug', false) || is_local();
    }
}

// ============== SERVICE CONFIG HELPERS ==============

if (!function_exists('db_config')) {
    function db_config() {
        $config = Configuration::getInstance();
        return $config->get("app.db");
    }
}

if (!function_exists('gf_config')) {
    function gf_config($key) {
        $config = Configuration::getInstance();
        return $config->get("gf.{$key}");
    }
}

if (!function_exists('ftp_config')) {
    function ftp_config($key) {
        $config = Configuration::getInstance();
        return $config->get("ftp.{$key}");
    }
}

if (!function_exists('sqlite_config')) {
    function sqlite_config() {
        $config = Configuration::getInstance();
        return $config->get('app.db', 'database/app.db');
    }
}

// Destination DB (PostgreSQL)
if (!function_exists('dest_db_config')) {
    function dest_db_config($key) {
        $config = Configuration::getInstance();
        return $config->get("dest.{$key}");
    }
}

if (!function_exists('source_db_config')) {
    function source_db_config($key) {
        $config = Configuration::getInstance();
        return $config->get("source.{$key}");
    }
}


// ============== DEBUG HELPERS (replaces System.php) ==============

if (!function_exists('app_debug_info')) {
    function app_debug_info() {
        return Configuration::getInstance()->getDebugInfo();
    }
}

if (!function_exists('dump_config')) {
    function dump_config($group = null) {
        if ($group) {
            return config($group);
        }
        return config_all();
    }
}