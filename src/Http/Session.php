<?php
/**
 * Enhanced Session Manager with Database Integration
 * File: apps/core/Session.php
 * 
 * Handles session management with dot notation, security features, and database tracking
 */
namespace Vireo\Framework\Http;

class Session {
    private static $instance = null;
    private $isActive = false;
    private $config = [];
    private $dbSessionId = null;
    private $userId = null;
    private $handler = null;
    
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
     * Constructor - Initialize session configuration
     */
    public function __construct() {
        $this->config = [
            'driver' => env('SESSION_DRIVER', 'file'),
            'name' => 'PHPSESSID',
            'lifetime' => (int) env('SESSION_LIFETIME', 43200), // 12 hours
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
            'check_ip' => true,
            'track_device' => true
        ];
    }
    
    /**
     * Start session with security configuration
     */
    public function start($config = []) {
        if ($this->isActive()) {
            return session_id();
        }
        
        // Merge custom config
        $this->config = array_merge($this->config, $config);
        
        // Set secure session configuration
        $this->configureSession();
        
        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
            $this->isActive = true;
            
            // Initialize session security
            $this->initializeSecurity();
            
        }
        
        return session_id();
    }
    
    /**
     * Configure session settings for security
     */
    private function configureSession() {
        // Set custom session handler based on driver (if not file)
        $driver = $this->config['driver'];

        if ($driver !== 'file') {
            try {
                $this->handler = $this->createSessionHandler($driver);
                session_set_save_handler($this->handler, true);
            } catch (\Exception $e) {
                // Log error and fallback to file sessions
                error_log("Session driver '{$driver}' failed: " . $e->getMessage() . ". Falling back to file sessions.");
                // Continue with default file sessions
            }
        }

        // Set session name
        session_name($this->config['name']);

        // Set session parameters
        session_set_cookie_params([
            'lifetime' => $this->config['lifetime'],
            'path' => $this->config['path'],
            'domain' => $this->config['domain'],
            'secure' => $this->config['secure'],
            'httponly' => $this->config['httponly'],
            'samesite' => $this->config['samesite']
        ]);

        // Additional PHP settings
        ini_set('session.cookie_httponly', $this->config['httponly']);
        ini_set('session.cookie_secure', $this->config['secure']);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', $this->config['samesite']);

        // Prevent session fixation
        ini_set('session.use_strict_mode', 1);

        // Set session cache settings
        session_cache_limiter('nocache');
        session_cache_expire($this->config['lifetime'] / 60);
    }

    /**
     * Create session handler based on driver
     *
     * @param string $driver Session driver name
     * @return \SessionHandlerInterface
     * @throws \Exception If driver not supported
     */
    private function createSessionHandler(string $driver): \SessionHandlerInterface
    {
        $config = array_merge($this->config, $this->loadDriverConfig($driver));

        return match ($driver) {
            'redis' => new \Vireo\Framework\Cache\SessionHandlers\RedisSessionHandler($config),
            'memcached' => new \Vireo\Framework\Cache\SessionHandlers\MemcachedSessionHandler($config),
            'memcache' => new \Vireo\Framework\Cache\SessionHandlers\MemcacheSessionHandler($config),
            'database' => new \Vireo\Framework\Cache\SessionHandlers\DatabaseSessionHandler($config),
            default => throw new \Exception("Unsupported session driver: {$driver}"),
        };
    }

    /**
     * Load driver-specific configuration
     *
     * @param string $driver Session driver name
     * @return array
     */
    private function loadDriverConfig(string $driver): array
    {
        return match ($driver) {
            'redis' => [
                'host' => env('REDIS_HOST', '127.0.0.1'),
                'port' => env('REDIS_PORT', 6379),
                'password' => env('REDIS_PASSWORD', null),
                'database' => env('REDIS_SESSION_DB', 1),
                'prefix' => env('REDIS_SESSION_PREFIX', 'pop_session:'),
                'persistent' => true,
                'persistent_id' => 'pop_session',
            ],
            'memcached' => [
                'servers' => [
                    [
                        'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                        'port' => env('MEMCACHED_PORT', 11211),
                        'weight' => 100,
                    ],
                ],
                'persistent_id' => env('MEMCACHED_PERSISTENT_ID', 'pop_session'),
                'prefix' => env('MEMCACHED_SESSION_PREFIX', 'pop_session:'),
            ],
            'memcache' => [
                'servers' => [
                    [
                        'host' => env('MEMCACHE_HOST', '127.0.0.1'),
                        'port' => env('MEMCACHE_PORT', 11211),
                    ],
                ],
                'prefix' => env('MEMCACHE_SESSION_PREFIX', 'pop_session:'),
            ],
            'database' => [
                'connection' => env('SESSION_DB_CONNECTION', 'app'),
                'table' => env('SESSION_DB_TABLE', 'sessions'),
            ],
            default => [],
        };
    }
    
    /**
     * Initialize session security measures
     */
    private function initializeSecurity() {
        // Set creation time
        if (!isset($_SESSION['__created'])) {
            $_SESSION['__created'] = time();
        }
        
        // Set last activity time
        $_SESSION['__last_activity'] = time();
        
        // Set user agent fingerprint
        if (!isset($_SESSION['__user_agent'])) {
            $_SESSION['__user_agent'] = md5($_SERVER['HTTP_USER_AGENT'] ?? '');
        }
        
        // Set IP address fingerprint  
        if (!isset($_SESSION['__ip_address'])) {
            $_SESSION['__ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['__last_regeneration'])) {
            $_SESSION['__last_regeneration'] = time();
        } elseif (time() - $_SESSION['__last_regeneration'] > 1800) { // 30 minutes
            $this->regenerate();
        }
        
        // Check for session hijacking
        $validate = $this->validateSecurity();

        if (!$validate) {
            route('login.show');
        }
    }
    
    /**
     * Get current user ID from session
     */
    public function getUserId() {
        return $this->userId ?? $_SESSION['__user_id'] ?? null;
    }
    
    
    /**
     * Serialize session data for database storage
     */
    private function serializeSessionData() {
        $data = $_SESSION;
        
        // Remove sensitive data from payload
        unset($data['__user_agent'], $data['__ip_address'], $data['__user_id']);
        
        return json_encode($data);
    }
    
    /**
     * Validate session security
     */
    private function validateSecurity() {
        $currentUserAgent = md5($_SERVER['HTTP_USER_AGENT'] ?? '');
        $currentIpAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // Check user agent
        if (isset($_SESSION['__user_agent']) && $_SESSION['__user_agent'] !== $currentUserAgent) {
            $this->destroy();
            error_log('Session security violation: User agent mismatch');
            return false;
        }
        
        // Check IP address (optional - can be disabled for mobile users)
        if ($this->config['check_ip'] && 
            isset($_SESSION['__ip_address']) && 
            $_SESSION['__ip_address'] !== $currentIpAddress) {
            $this->destroy();
            error_log('Session security violation: IP address mismatch');
            return false;
        }
        
        // Check session age
        if (isset($_SESSION['__created']) && 
            time() - $_SESSION['__created'] > $this->config['lifetime']) {
            $this->destroy();
            error_log('Session expired');
            return false;
        }
        
        // Check activity timeout
        if (isset($_SESSION['__last_activity']) && 
            time() - $_SESSION['__last_activity'] > 1800) { // 30 minutes inactivity
            $this->destroy();
            error_log('Session timeout due to inactivity');
            return false;
        }

        return true;
    }
    
    /**
     * Regenerate session ID
     */
    public function regenerate($deleteOldSession = true) {
        if ($this->isActive()) {
            $oldSessionId = session_id();
            session_regenerate_id($deleteOldSession);
            $newSessionId = session_id();
            $_SESSION['__last_regeneration'] = time();
            
        }
        return session_id();
    }
    
    
    /**
     * Check if session is active
     */
    public function isActive() {
        return $this->isActive || session_status() === PHP_SESSION_ACTIVE;
    }
    
    /**
     * Get session value using dot notation
     */
    public function get($key, $default = null) {
        if (!$this->isActive()) {
            $this->start();
        }
        
        if (strpos($key, '.') !== false) {
            return $this->getDotNotation($key, $default);
        }
        
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Get session value using dot notation
     */
    private function getDotNotation($key, $default) {
        $keys = explode('.', $key);
        $value = $_SESSION;
        
        foreach ($keys as $segment) {
            if (isset($value[$segment])) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
    
    /**
     * Set session value using dot notation
     */
    public function set($key, $value) {
        if (!$this->isActive()) {
            $this->start();
        }
        
        if (strpos($key, '.') !== false) {
            $this->setDotNotation($key, $value);
        } else {
            $_SESSION[$key] = $value;
        }
        
        // Update last activity
        $_SESSION['__last_activity'] = time();

    }
    
    /**
     * Set session value using dot notation
     */
    private function setDotNotation($key, $value) {
        $keys = explode('.', $key);
        $session = &$_SESSION;
        
        while (count($keys) > 1) {
            $segment = array_shift($keys);
            if (!isset($session[$segment]) || !is_array($session[$segment])) {
                $session[$segment] = [];
            }
            $session = &$session[$segment];
        }
        
        $session[array_shift($keys)] = $value;
    }
    
    /**
     * Check if session has key
     */
    public function has($key) {
        return $this->get($key) !== null;
    }
    
    /**
     * Remove session key using dot notation
     */
    public function remove($key) {
        if (!$this->isActive()) {
            return false;
        }
        
        if (strpos($key, '.') !== false) {
            $this->removeDotNotation($key);
        } else {
            unset($_SESSION[$key]);
        }
        
        
        return true;
    }
    
    /**
     * Remove session value using dot notation
     */
    private function removeDotNotation($key) {
        $keys = explode('.', $key);
        $session = &$_SESSION;
        
        while (count($keys) > 1) {
            $segment = array_shift($keys);
            if (!isset($session[$segment])) {
                return;
            }
            $session = &$session[$segment];
        }
        
        unset($session[array_shift($keys)]);
    }
    
    /**
     * Get all session data (excluding security keys)
     */
    public function all() {
        if (!$this->isActive()) {
            $this->start();
        }
        
        $data = $_SESSION;
        
        // Remove security keys from output
        $securityKeys = ['__created', '__last_activity', '__user_agent', '__ip_address', '__last_regeneration', '__user_id'];
        foreach ($securityKeys as $securityKey) {
            unset($data[$securityKey]);
        }
        
        return $data;
    }
    
    /**
     * Clear all session data
     */
    public function clear() {
        if ($this->isActive()) {
            $_SESSION = [];
            // Re-initialize security after clearing
            $this->initializeSecurity();
        }
    }
    
    /**
     * Destroy session completely
     */
    public function destroy() {
        if ($this->isActive()) {

            
            // Clear session data
            $_SESSION = [];
            
            // Delete session cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            // Destroy session
            session_destroy();
            $this->isActive = false;
            $this->dbSessionId = null;
            $this->userId = null;
        }
    }
    
    /**
     * Flash data for one request
     */
    public function flash($key, $value) {
        $this->set("__flash.$key", $value);
    }
    
    /**
     * Get flash data
     */
    public function getFlash($key, $default = null) {
        $value = $this->get("__flash.$key", $default);
        $this->remove("__flash.$key");
        return $value;
    }
    
    /**
     * Keep flash data for another request
     */
    public function keepFlash($key) {
        $value = $this->get("__flash.$key");
        if ($value !== null) {
            $this->flash($key, $value);
        }
    }
    
    /**
     * Get session ID
     */
    public function getId() {
        return session_id();
    }
    
    /**
     * Set session configuration
     */
    public function setConfig($key, $value) {
        $this->config[$key] = $value;
    }
}