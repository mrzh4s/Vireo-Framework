<?php
/**
 * Cookie Global Helper Functions
 * File: apps/core/helpers/cookie.php
 * 
 * Add these to your global helpers for easy cookie management
 */

if (!function_exists('set_secure_cookie')) {
    /**
     * Set a secure cookie with proper security flags
     * 
     * @param string $name Cookie name
     * @param string $value Cookie value
     * @param int $expires Expiration time (default: 24 hours)
     * @param string $path Cookie path (default: '/')
     * @param string $domain Cookie domain
     * @param bool $secure HTTPS only (auto-detect)
     * @param bool $httpOnly HTTP only flag (default: true)
     * @param string $sameSite SameSite attribute (default: 'Strict')
     * @return bool
     */
    function set_secure_cookie($name, $value, $expires = null, $path = '/', $domain = '', $secure = null, $httpOnly = true, $sameSite = 'Strict') {
        $options = [
            'expires' => $expires ?: time() + 86400, // Default 24 hours
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure ?? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
            'httponly' => $httpOnly,
            'samesite' => $sameSite
        ];
        
        return setcookie($name, $value, $options);
    }
}

if (!function_exists('get_cookie')) {
    /**
     * Get cookie value with optional default
     * 
     * @param string $name Cookie name
     * @param mixed $default Default value if cookie doesn't exist
     * @return mixed
     */
    function get_cookie($name, $default = null) {
        return $_COOKIE[$name] ?? $default;
    }
}

if (!function_exists('has_cookie')) {
    /**
     * Check if cookie exists
     * 
     * @param string $name Cookie name
     * @return bool
     */
    function has_cookie($name) {
        return isset($_COOKIE[$name]);
    }
}

if (!function_exists('delete_cookie')) {
    /**
     * Delete a cookie by setting it to expire in the past
     * 
     * @param string $name Cookie name
     * @param string $path Cookie path (default: '/')
     * @param string $domain Cookie domain
     * @return bool
     */
    function delete_cookie($name, $path = '/', $domain = '') {
        $options = [
            'expires' => time() - 3600, // 1 hour ago
            'path' => $path,
            'domain' => $domain,
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Strict'
        ];
        
        unset($_COOKIE[$name]);
        return setcookie($name, '', $options);
    }
}

if (!function_exists('set_user_cookie')) {
    /**
     * Set user authentication cookie with proper security
     * 
     * @param string $name Cookie name
     * @param string $value Cookie value
     * @param int $expires Expiration time (default: 24 hours)
     * @return bool
     */
    function set_user_cookie($name, $value, $expires = null) {
        return set_secure_cookie("APP_$name", $value, $expires, '/', '', null, true, 'Strict');
    }
}

if (!function_exists('get_user_cookie')) {
    /**
     * Get user cookie with app prefix
     * 
     * @param string $name Cookie name (without APP_ prefix)
     * @param mixed $default Default value
     * @return mixed
     */
    function get_user_cookie($name, $default = null) {
        return get_cookie("APP_$name", $default);
    }
}

if (!function_exists('has_user_cookie')) {
    /**
     * Check if user cookie exists
     * 
     * @param string $name Cookie name (without APP_ prefix)
     * @return bool
     */
    function has_user_cookie($name) {
        return has_cookie("APP_$name");
    }
}

if (!function_exists('delete_user_cookie')) {
    /**
     * Delete user cookie
     * 
     * @param string $name Cookie name (without APP_ prefix)
     * @return bool
     */
    function delete_user_cookie($name) {
        return delete_cookie("APP_$name");
    }
}

if (!function_exists('set_auth_cookies')) {
    /**
     * Set all authentication cookies for a user
     * 
     * @param array $userData User data array
     * @param int $expires Expiration time (default: 24 hours)
     * @return bool
     */
    function set_auth_cookies($userData, $expires = null) {
        $expiry = $expires ?: time() + 86400; // 24 hours
        $success = true;

        // User data
        $success &= set_user_cookie('USER', base64_encode(json_encode($userData)), $expiry);

        //Login Data
        $success &= set_user_cookie('LOGIN_TIME', time(), $expiry);
        $success &= set_user_cookie('LOGIN_IP', $_SERVER['REMOTE_ADDR'] ?? 'unknown', $expiry);
        
        // Authentication status
        $success &= set_user_cookie('AUTHENTICATED', 'true', $expiry);
        
        return $success;
    }
}

if (!function_exists('get_auth_user')) {
    /**
     * Get authenticated user data from cookies
     * 
     * @return array|null User data array or null if not authenticated
     */
    function get_auth_user() {
        if (!is_cookie_authenticated()) {
            return null;
        }

        $user = json_decode(base64_decode(get_user_cookie('USER')));
        
        $roles = $user->roles ?? [];
        $groups = $user->groups ?? [];
        
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => json_decode($roles, true) ?: [],
            'groups' => json_decode($groups, true) ?: [],
            'session_id' => get_user_cookie('SESSION'),
        ];
    }
}

if (!function_exists('is_cookie_authenticated')) {
    /**
     * Check if user is authenticated via cookies
     * 
     * @return bool
     */
    function is_cookie_authenticated() {
        if (!has_user_cookie('AUTHENTICATED') || !has_user_cookie('USER')) {
            return false;
        }
        
        // Check session expiration (24 hours)
        $loginTime = get_user_cookie('LOGIN_TIME', 0);
        if (time() - $loginTime > 86400) {
            clear_auth_cookies();
            return false;
        }
        
        return true;
    }
}

if (!function_exists('clear_auth_cookies')) {
    /**
     * Clear all authentication cookies (logout)
     * 
     * @return bool
     */
    function clear_auth_cookies() {
        $cookieNames = [
            'AUTHENTICATED', 'USER', 'SESSION', 'LOGIN_TIME', 'LOGIN_IP'
        ];
        
        $success = true;
        foreach ($cookieNames as $name) {
            if (has_user_cookie($name)) {
                $success &= delete_user_cookie($name);
            }
        }
        
        return $success;
    }
}

if (!function_exists('refresh_auth_cookies')) {
    /**
     * Refresh authentication cookies (extend expiration)
     * 
     * @param int $expires New expiration time
     * @return bool
     */
    function refresh_auth_cookies($expires = null) {
        if (!is_cookie_authenticated()) {
            return false;
        }
        
        $userData = get_auth_user();
        if ($userData) {
            return set_auth_cookies($userData, $expires);
        }
        
        return false;
    }
}

if (!function_exists('user_has_role')) {
    /**
     * Check if authenticated user has specific role
     * 
     * @param string $role Role name to check
     * @return bool
     */
    function user_has_role($role) {
        if (!is_cookie_authenticated()) {
            return false;
        }

        $user = json_decode(base64_decode(get_user_cookie('USER')));
        
        $roles = json_decode( $user->roles ?? '[]', true) ?: [];
        return in_array($role, $roles);
    }
}

if (!function_exists('user_has_group')) {
    /**
     * Check if authenticated user belongs to specific group
     * 
     * @param string $group Group name to check
     * @return bool
     */
    function user_has_group($group) {
        if (!is_cookie_authenticated()) {
            return false;
        }

        $user = json_decode(base64_decode(get_user_cookie('USER')));
        
        $groups = json_decode($user->groups ?? '[]', true) ?: [];
        return in_array($group, $groups);
    }
}

if (!function_exists('current_user_id')) {
    /**
     * Get current authenticated user ID
     * 
     * @return string|null
     */
    function current_user_id() {

        $user = json_decode(base64_decode(get_user_cookie('USER')));
        return $user->id ?? null;
    }
}

if (!function_exists('current_user_name')) {
    /**
     * Get current authenticated user name
     * 
     * @return string|null
     */
    function current_user_name() {
        $user = json_decode(base64_decode(get_user_cookie('USER')));
        return $user->name ?? null;
    }
}

if (!function_exists('current_user_email')) {
    /**
     * Get current authenticated user email
     * 
     * @return string|null
     */
    function current_user_email() {
        $user = json_decode(base64_decode(get_user_cookie('USER')));
        return $user->email ?? null;
    }
}