<?php

use Framework\Security\CSRFToken;
/**
 * CSRF Helper Functions - Add these to your global helpers
 */

if (!function_exists('csrf_token')) {
    /**
     * Get or generate CSRF token
     */
    function csrf_token() {
        return CSRFToken::generate();
    }
}

if (!function_exists('csrf_verify')) {
    /**
     * Verify CSRF token from request
     */
    function csrf_verify($token = null) {
        if ($token === null) {
            $token = CSRFToken::getFromRequest();
        }

        if (!$token) {
            return false;
        }
        
        return CSRFToken::validate($token);
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate CSRF hidden input field for forms
     */
    function csrf_field() {
        $token = csrf_token();
        return '<input type="hidden" name="_token" value="' . $token . '">';
    }
}

if (!function_exists('csrf_meta')) {
    /**
     * Generate CSRF meta tag for head section
     */
    function csrf_meta() {
        $token = csrf_token();
        return '<meta name="csrf-token" content="' . $token . '">';
    }
}

if (!function_exists('secure_session')) {
    /**
     * Start secure session
     */
    function secure_session() {
        if (session_status() === PHP_SESSION_NONE) {
            // Set secure session parameters
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_samesite', 'Strict');
            
            session_start();
            
            // Validate session security
            CSRFToken::validateSession();
        }
        
        return session_id();
    }
}