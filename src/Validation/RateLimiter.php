<?php

namespace Vireo\Framework\Validation;

use Vireo\Framework\Cache\CacheManager;
use Vireo\Framework\Cache\CacheInterface;
use Exception;

/**
 * RateLimiter - Rate limiting service using cache backends
 *
 * Singleton service that provides request throttling using the cache system.
 * Supports file, Redis, Memcached, and database backends via cache stores.
 * Refactored to use unified cache interface for all backends.
 */
class RateLimiter
{
    /**
     * @var RateLimiter|null Singleton instance
     */
    private static ?RateLimiter $instance = null;

    /**
     * @var CacheInterface Cache store instance
     */
    private CacheInterface $cache;

    /**
     * @var array Configuration
     */
    private array $config = [];

    /**
     * @var string Cache key prefix
     */
    private string $prefix = 'rate_limit:';

    /**
     * Get singleton instance
     *
     * @return RateLimiter
     */
    public static function getInstance(): RateLimiter
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Private constructor - Singleton pattern
     */
    private function __construct()
    {
        $this->loadConfiguration();
        $this->initializeBackend();
    }

    /**
     * Load rate limit configuration
     *
     * @return void
     */
    private function loadConfiguration(): void
    {
        $configPath = defined('ROOT_PATH') ? ROOT_PATH . '/Config/RateLimit.php' : __DIR__ . '/../../Config/RateLimit.php';

        if (file_exists($configPath)) {
            $this->config = require $configPath;
        } else {
            // Default configuration
            $this->config = [
                'backend' => 'file',
                'default' => [
                    'max_attempts' => 60,
                    'decay_minutes' => 1,
                ],
            ];
        }
    }

    /**
     * Initialize cache backend
     *
     * @return void
     */
    private function initializeBackend(): void
    {
        $backend = $this->config['backend'] ?? 'file';

        try {
            // Get cache store for rate limiting
            // If backend matches a cache store name, use it; otherwise use default
            $this->cache = CacheManager::getInstance()->store($backend);
        } catch (Exception $e) {
            // Fallback to file cache if backend fails
            error_log("RateLimiter backend '{$backend}' failed: " . $e->getMessage() . ". Falling back to file cache.");
            $this->cache = CacheManager::getInstance()->store('file');
        }
    }

    /**
     * Attempt to perform an action
     * Returns true if allowed, false if too many attempts
     *
     * @param string $key Unique identifier for the rate limit
     * @param int $maxAttempts Maximum number of attempts
     * @param int $decayMinutes Time window in minutes
     * @return bool
     */
    public function attempt(string $key, int $maxAttempts, int $decayMinutes = 1): bool
    {
        if ($this->tooManyAttempts($key, $maxAttempts)) {
            return false;
        }

        $this->hit($key, $decayMinutes);
        return true;
    }

    /**
     * Check if too many attempts have been made
     *
     * @param string $key Unique identifier
     * @param int $maxAttempts Maximum attempts allowed
     * @return bool
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->attempts($key) >= $maxAttempts;
    }

    /**
     * Increment the attempt counter
     *
     * @param string $key Unique identifier
     * @param int $decayMinutes Time window in minutes
     * @return int New attempt count
     */
    public function hit(string $key, int $decayMinutes = 1): int
    {
        $cacheKey = $this->prefix . $key;
        $ttl = $decayMinutes * 60; // Convert minutes to seconds

        try {
            // Increment counter
            $attempts = $this->cache->increment($cacheKey, 1);

            if ($attempts === false || $attempts === 1) {
                // First hit or increment failed - set initial value with TTL
                $this->cache->set($cacheKey, 1, $ttl);
                return 1;
            }

            // Update TTL on each hit to reset the window
            $this->cache->setTtl($cacheKey, $ttl);

            return $attempts;
        } catch (Exception $e) {
            error_log("RateLimiter hit error for key '{$key}': " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get the number of attempts
     *
     * @param string $key Unique identifier
     * @return int
     */
    public function attempts(string $key): int
    {
        try {
            $cacheKey = $this->prefix . $key;
            return (int) $this->cache->get($cacheKey, 0);
        } catch (Exception $e) {
            error_log("RateLimiter attempts error for key '{$key}': " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Reset attempts for a key
     *
     * @param string $key Unique identifier
     * @return void
     */
    public function resetAttempts(string $key): void
    {
        try {
            $cacheKey = $this->prefix . $key;
            $this->cache->delete($cacheKey);
        } catch (Exception $e) {
            error_log("RateLimiter reset error for key '{$key}': " . $e->getMessage());
        }
    }

    /**
     * Get remaining attempts
     *
     * @param string $key Unique identifier
     * @param int $maxAttempts Maximum attempts allowed
     * @return int
     */
    public function retriesLeft(string $key, int $maxAttempts): int
    {
        $attempts = $this->attempts($key);
        return max(0, $maxAttempts - $attempts);
    }

    /**
     * Get seconds until rate limit resets
     *
     * @param string $key Unique identifier
     * @return int Seconds until reset
     */
    public function availableIn(string $key): int
    {
        try {
            $cacheKey = $this->prefix . $key;
            $ttl = $this->cache->getTtl($cacheKey);

            return $ttl ?? 0;
        } catch (Exception $e) {
            error_log("RateLimiter availableIn error for key '{$key}': " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Clear all rate limits (useful for testing)
     *
     * Note: This clears the entire cache store, not just rate limit keys.
     * Use with caution in production.
     *
     * @return void
     */
    public function clear(): void
    {
        try {
            // Note: This clears the entire cache store
            // Ideally we'd only clear rate_limit:* keys, but that requires tag support
            $this->cache->clear();
        } catch (Exception $e) {
            error_log("RateLimiter clear error: " . $e->getMessage());
        }
    }

    /**
     * Throttle by IP address
     *
     * @param int $maxAttempts Maximum attempts
     * @param int $decayMinutes Time window
     * @return bool
     */
    public function forIp(int $maxAttempts, int $decayMinutes = 1): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'ip:' . $ip;

        return $this->attempt($key, $maxAttempts, $decayMinutes);
    }

    /**
     * Throttle by user ID
     *
     * @param int|string $userId User ID
     * @param int $maxAttempts Maximum attempts
     * @param int $decayMinutes Time window
     * @return bool
     */
    public function forUser(int|string $userId, int $maxAttempts, int $decayMinutes = 1): bool
    {
        $key = 'user:' . $userId;

        return $this->attempt($key, $maxAttempts, $decayMinutes);
    }

    /**
     * Get the cache instance (for advanced operations)
     *
     * @return CacheInterface
     */
    public function getCache(): CacheInterface
    {
        return $this->cache;
    }

    /**
     * Prevent cloning of singleton
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserialization of singleton
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}
