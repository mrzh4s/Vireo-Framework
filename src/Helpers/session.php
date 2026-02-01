<?php

/**
 * Session Helper Functions (Session class only)
 * File: session.php
 *
 * Session-specific helper functions that work only with the Session class
 */

use Framework\Http\Session;

// ============== CORE SESSION HELPERS ==============

if (!function_exists('session_start')) {
    /**
     * Start session with optional configuration
     */
    function session_start($config = []) {
        return Session::getInstance()->start($config);
    }
}

if (!function_exists('session')) {
    /**
     * Session helper function with dot notation support
     */
    function session($key = null, $default = null) {
        $session = Session::getInstance();
        
        if ($key === null) {
            return $session->all();
        }
        
        return $session->get($key, $default);
    }
}

if (!function_exists('session_set')) {
    /**
     * Set session value with dot notation support
     */
    function session_set($key, $value) {
        return Session::getInstance()->set($key, $value);
    }
}

if (!function_exists('session_has')) {
    /**
     * Check if session has key
     */
    function session_has($key) {
        return Session::getInstance()->has($key);
    }
}

if (!function_exists('session_remove')) {
    /**
     * Remove session key
     */
    function session_remove($key) {
        return Session::getInstance()->remove($key);
    }
}

if (!function_exists('session_clear')) {
    /**
     * Clear all session data
     */
    function session_clear() {
        return Session::getInstance()->clear();
    }
}

if (!function_exists('session_destroy')) {
    /**
     * Destroy session completely
     */
    function session_destroy() {
        return Session::getInstance()->destroy();
    }
}

if (!function_exists('session_regenerate')) {
    /**
     * Regenerate session ID
     */
    function session_regenerate($deleteOld = true) {
        return Session::getInstance()->regenerate($deleteOld);
    }
}

// ============== FLASH MESSAGE HELPERS ==============

if (!function_exists('session_flash')) {
    /**
     * Set flash message
     */
    function session_flash($key, $value) {
        return Session::getInstance()->flash($key, $value);
    }
}

if (!function_exists('session_get_flash')) {
    /**
     * Get flash message
     */
    function session_get_flash($key, $default = null) {
        return Session::getInstance()->getFlash($key, $default);
    }
}

if (!function_exists('session_keep_flash')) {
    /**
     * Keep flash message for another request
     */
    function session_keep_flash($key) {
        return Session::getInstance()->keepFlash($key);
    }
}

// ============== SESSION INFO HELPERS ==============

if (!function_exists('session_id')) {
    /**
     * Get current session ID
     */
    function session_id() {
        return Session::getInstance()->getId();
    }
}

if (!function_exists('session_is_active')) {
    /**
     * Check if session is active
     */
    function session_is_active() {
        return Session::getInstance()->isActive();
    }
}

if (!function_exists('session_user_id')) {
    /**
     * Get current user ID from session
     */
    function session_user_id() {
        return Session::getInstance()->getUserId();
    }
}

// ============== CONVENIENCE FLASH HELPERS ==============

if (!function_exists('flash_success')) {
    /**
     * Set success flash message
     */
    function flash_success($message) {
        return session_flash('success', $message);
    }
}

if (!function_exists('flash_error')) {
    /**
     * Set error flash message
     */
    function flash_error($message) {
        return session_flash('error', $message);
    }
}

if (!function_exists('flash_warning')) {
    /**
     * Set warning flash message
     */
    function flash_warning($message) {
        return session_flash('warning', $message);
    }
}

if (!function_exists('flash_info')) {
    /**
     * Set info flash message
     */
    function flash_info($message) {
        return session_flash('info', $message);
    }
}

if (!function_exists('get_flash_messages')) {
    /**
     * Get all flash messages
     */
    function get_flash_messages() {
        return [
            'success' => session_get_flash('success'),
            'error' => session_get_flash('error'),
            'warning' => session_get_flash('warning'),
            'info' => session_get_flash('info')
        ];
    }
}

if (!function_exists('has_flash_messages')) {
    /**
     * Check if there are any flash messages
     */
    function has_flash_messages() {
        return session_has('__flash.success') ||
               session_has('__flash.error') ||
               session_has('__flash.warning') ||
               session_has('__flash.info');
    }
}

// ============== VALIDATION ERROR HELPERS ==============

if (!function_exists('validation_errors')) {
    /**
     * Set validation errors for Inertia
     *
     * @param array $errors Associative array of field => error message
     */
    function validation_errors(array $errors) {
        $_SESSION['validation_errors'] = $errors;
    }
}

if (!function_exists('validation_error')) {
    /**
     * Add a single validation error
     *
     * @param string $field Field name
     * @param string $message Error message
     */
    function validation_error($field, $message) {
        if (!isset($_SESSION['validation_errors'])) {
            $_SESSION['validation_errors'] = [];
        }
        $_SESSION['validation_errors'][$field] = $message;
    }
}

if (!function_exists('has_validation_errors')) {
    /**
     * Check if there are validation errors
     */
    function has_validation_errors() {
        return !empty($_SESSION['validation_errors']);
    }
}

if (!function_exists('clear_validation_errors')) {
    /**
     * Clear all validation errors
     */
    function clear_validation_errors() {
        unset($_SESSION['validation_errors']);
    }
}

// ============== SESSION UTILITY HELPERS ==============

if (!function_exists('session_config')) {
    /**
     * Set session configuration
     */
    function session_config($key, $value) {
        return Session::getInstance()->setConfig($key, $value);
    }
}


// ============== FORMAT HELPER FUNCTIONS ==============

if (!function_exists('format_session_time')) {
    /**
     * Format session time for display
     */
    function format_session_time($datetime) {
        if (!$datetime) {
            return 'Never';
        }
        
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 2592000) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return date('M j, Y', $time);
        }
    }
}

if (!function_exists('get_device_icon')) {
    /**
     * Get appropriate icon class for device type
     */
    function get_device_icon($deviceType) {
        switch (strtolower($deviceType)) {
            case 'mobile':
                return 'icon-mobile';
            case 'tablet':
                return 'icon-tablet';
            case 'desktop':
            default:
                return 'icon-desktop';
        }
    }
}