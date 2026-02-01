<?php
/**
 * Security Class for CSRF Protection and Security Helpers
 * File: apps/core/Security.php
 */
namespace Vireo\Framework\Security;

class CSRFToken {
    
    /**
     * Generate CSRF token and store in session
     */
    public static function generate() {
        secure_session();
        if (!session_has('csrf_token')) {
            session_set('csrf_token', bin2hex(random_bytes(32)));
            session_set('csrf_token_time', time());
        }
        
        return session('csrf_token');
    }
    
    /**
     * Validate CSRF token
     */
    public static function validate($token) {
        secure_session();
        
        // Check if token exists in session
        if (!session_has('csrf_token')) {
            return false;
        }
        
        // Check token age (expires after 2 hours)
        if (session_has('csrf_token_time')) {
            $tokenAge = time() - session('csrf_token_time');
            if ($tokenAge > 7200) { // 2 hours
                session_remove('csrf_token');
                session_remove('csrf_token_time');
                return false;
            }
        }
        
        // Use hash_equals to prevent timing attacks
        return hash_equals(session('csrf_token'), $token);
    }
    
    /**
     * Get CSRF token from request headers or POST data
     */
    public static function getFromRequest() {
        // Try X-CSRF-TOKEN header first
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        
        // Try alternative header formats
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        
        // Try from POST data
        if (isset($_POST['_token'])) {
            return $_POST['_token'];
        }
        
        // Try from JSON request body
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input && isset($input['_token'])) {
            return $input['_token'];
        }
        
        return null;
    }
    
    /**
     * Refresh CSRF token (generate new one)
     */
    public static function refresh() {

        secure_session();

        session_remove('csrf_token');
        session_remove('csrf_token_time');
        
        return self::generate();
    }
    
    /**
     * Check if CSRF token needs refresh
     */
    public static function needs() {
        if (!session_has('csrf_token_time')) {
            return true;
        }
        
        $tokenAge = time() - session('csrf_token_time');
        return $tokenAge > 3600; // Refresh after 1 hour
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitize($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Generate secure random string
     */
    public static function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Validate session security
     */
    public static function validateSession() {
        if (session_status() === PHP_SESSION_NONE) {
            return false;
        }
        
        // Check session fixation
        if (!session_has('created_at')) {
            session_set('created_at', time());
        }
        
        // Regenerate session ID every 30 minutes
        if (session_has('last_regeneration')) {
            if (time() - session('last_regeneration') > 1800) {
                session_regenerate_id(true);
                session_set('last_regeneration', time());
            }
        } else {
            session_set('last_regeneration', time());
        }
        
        return true;
    }
}
