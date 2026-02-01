<?php

use Framework\Environment;
/**
 * Global helper functions for environment
 * ONLY CORE ENVIRONMENT FUNCTIONS - App functions moved to Configuration.php
 */

if (!function_exists('env')) {
    /**
     * Get environment variable with type conversion
     * NOTE: This is the direct Environment version
     * Configuration.php also has an env() function that acts as a proxy
     */
    function env($key, $default = null) {
        return Environment::getInstance()->get($key, $default);
    }
}

if (!function_exists('env_set')) {
    /**
     * Set environment variable
     */
    function env_set($key, $value) {
        return Environment::getInstance()->set($key, $value);
    }
}

if (!function_exists('env_has')) {
    /**
     * Check if environment variable exists
     */
    function env_has($key) {
        return Environment::getInstance()->has($key);
    }
}