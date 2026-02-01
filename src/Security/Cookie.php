<?php
/**
 * Enhanced PHP Backend for Secure Cookie Authentication
 * File: apps/core/CookieAuth.php
 */
namespace Vireo\Framework\Security;


class Cookie {
    
    const COOKIE_PREFIX = 'app_';
    const SESSION_DURATION = 86400; // 24 hours
    
    /**
     * Cookie configuration for security
     */
    private static function getCookieOptions($maxAge = null) {
        return [
            'expires' => time() + ($maxAge ?? self::SESSION_DURATION),
            'path' => '/',
            'domain' => '', // Let PHP determine the domain
            'secure' => isset($_SERVER['HTTPS']), // HTTPS only in production
            'httponly' => true, // Prevent XSS
            'samesite' => 'Strict' // CSRF protection
        ];
    }
    
    /**
     * Set user data in secure cookies after successful login
     */
    public static function setUserCookies($userData) {
        $cookieOptions = self::getCookieOptions();
        
        // Core user data (always needed by backend)
        setcookie(self::COOKIE_PREFIX . 'user_id', $userData['id'], $cookieOptions);
        setcookie(self::COOKIE_PREFIX . 'user_name', $userData['name'], $cookieOptions);
        setcookie(self::COOKIE_PREFIX . 'user_email', $userData['email'], $cookieOptions);
        setcookie(self::COOKIE_PREFIX . 'first_name', $userData['first_name'] ?? '', $cookieOptions);
        setcookie(self::COOKIE_PREFIX . 'last_name', $userData['last_name'] ?? '', $cookieOptions);
        setcookie(self::COOKIE_PREFIX . 'employee_id', $userData['employee_id'] ?? '', $cookieOptions);
        
        // Roles and groups (JSON encoded)
        $roles = is_array($userData['roles']) ? $userData['roles'] : [];
        $groups = is_array($userData['groups']) ? $userData['groups'] : [];
        
        setcookie(self::COOKIE_PREFIX . 'roles', json_encode(array_column($roles, 'name')), $cookieOptions);
        setcookie(self::COOKIE_PREFIX . 'groups', json_encode(array_column($groups, 'name')), $cookieOptions);
        
        // Session management
        setcookie(self::COOKIE_PREFIX . 'session_id', $userData['session_id'] ?? session_id(), $cookieOptions);
        setcookie(self::COOKIE_PREFIX . 'login_time', time(), $cookieOptions);
        setcookie(self::COOKIE_PREFIX . 'login_ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown', $cookieOptions);
        
        // Authentication status
        setcookie(self::COOKIE_PREFIX . 'authenticated', 'true', $cookieOptions);
    }
    
    /**
     * Get user data from cookies
     */
    public static function getUserFromCookies() {
        if (!self::isAuthenticated()) {
            return null;
        }
        
        return [
            'id' => $_COOKIE[self::COOKIE_PREFIX . 'user_id'] ?? null,
            'name' => $_COOKIE[self::COOKIE_PREFIX . 'user_name'] ?? null,
            'email' => $_COOKIE[self::COOKIE_PREFIX . 'user_email'] ?? null,
            'first_name' => $_COOKIE[self::COOKIE_PREFIX . 'first_name'] ?? null,
            'last_name' => $_COOKIE[self::COOKIE_PREFIX . 'last_name'] ?? null,
            'employee_id' => $_COOKIE[self::COOKIE_PREFIX . 'employee_id'] ?? null,
            'roles' => json_decode($_COOKIE[self::COOKIE_PREFIX . 'roles'] ?? '[]', true),
            'groups' => json_decode($_COOKIE[self::COOKIE_PREFIX . 'groups'] ?? '[]', true),
            'session_id' => $_COOKIE[self::COOKIE_PREFIX . 'session_id'] ?? null,
            'login_time' => $_COOKIE[self::COOKIE_PREFIX . 'login_time'] ?? null,
            'login_ip' => $_COOKIE[self::COOKIE_PREFIX . 'login_ip'] ?? null,
        ];
    }
    
    /**
     * Check if user is authenticated via cookies
     */
    public static function isAuthenticated() {
        if (!isset($_COOKIE[self::COOKIE_PREFIX . 'authenticated'])) {
            return false;
        }
        
        if (!isset($_COOKIE[self::COOKIE_PREFIX . 'user_id'])) {
            return false;
        }
        
        // Check session expiration
        $loginTime = $_COOKIE[self::COOKIE_PREFIX . 'login_time'] ?? 0;
        if (time() - $loginTime > self::SESSION_DURATION) {
            self::clearUserCookies();
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if user has specific role
     */
    public static function hasRole($roleName) {
        if (!self::isAuthenticated()) {
            return false;
        }
        
        $roles = json_decode($_COOKIE[self::COOKIE_PREFIX . 'roles'] ?? '[]', true);
        return in_array($roleName, $roles);
    }
    
    /**
     * Check if user belongs to specific group
     */
    public static function hasGroup($groupName) {
        if (!self::isAuthenticated()) {
            return false;
        }
        
        $groups = json_decode($_COOKIE[self::COOKIE_PREFIX . 'groups'] ?? '[]', true);
        return in_array($groupName, $groups);
    }
    
    /**
     * Get current user ID
     */
    public static function getUserId() {
        return $_COOKIE[self::COOKIE_PREFIX . 'user_id'] ?? null;
    }
    
    /**
     * Get current user name
     */
    public static function getUserName() {
        return $_COOKIE[self::COOKIE_PREFIX . 'user_name'] ?? null;
    }
    
    /**
     * Clear all user cookies (logout)
     */
    public static function clearUserCookies() {
        $cookieNames = [
            'authenticated', 'user_id', 'user_name', 'user_email', 
            'first_name', 'last_name', 'employee_id', 'roles', 'groups',
            'session_id', 'login_time', 'login_ip'
        ];
        
        foreach ($cookieNames as $name) {
            if (isset($_COOKIE[self::COOKIE_PREFIX . $name])) {
                setcookie(self::COOKIE_PREFIX . $name, '', time() - 3600, '/');
                unset($_COOKIE[self::COOKIE_PREFIX . $name]);
            }
        }
    }
    
    /**
     * Refresh user session (extend expiration)
     */
    public static function refreshSession() {
        if (!self::isAuthenticated()) {
            return false;
        }
        
        $userData = self::getUserFromCookies();
        if ($userData) {
            // Reset cookies with new expiration
            self::setUserCookies($userData);
            return true;
        }
        
        return false;
    }
    
    /**
     * Validate session security
     */
    public static function validateSession() {
        if (!self::isAuthenticated()) {
            return false;
        }
        
        // Check IP consistency (optional - might cause issues with mobile users)
        // $currentIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        // $loginIP = $_COOKIE[self::COOKIE_PREFIX . 'login_ip'] ?? '';
        // if ($currentIP !== $loginIP) {
        //     self::clearUserCookies();
        //     return false;
        // }
        
        return true;
    }
}
