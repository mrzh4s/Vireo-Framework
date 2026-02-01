<?php

namespace Framework\Cache;

/**
 * CacheInterface - PSR-16 Simple Cache compatible interface
 *
 * Defines the contract for all cache drivers in the Pop Framework.
 * Supports basic operations, TTL management, atomic operations, and advanced patterns.
 */
interface CacheInterface
{
    // =========================================================================
    // Basic Cache Operations
    // =========================================================================

    /**
     * Get a value from the cache
     *
     * @param string $key The cache key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The cached value or default
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store a value in the cache
     *
     * @param string $key The cache key
     * @param mixed $value The value to cache
     * @param int|null $ttl Time to live in seconds (null = forever)
     * @return bool True on success, false on failure
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Check if a key exists in the cache
     *
     * @param string $key The cache key
     * @return bool True if exists, false otherwise
     */
    public function has(string $key): bool;

    /**
     * Delete a value from the cache
     *
     * @param string $key The cache key
     * @return bool True on success, false on failure
     */
    public function delete(string $key): bool;

    /**
     * Clear all values from the cache
     *
     * @return bool True on success, false on failure
     */
    public function clear(): bool;

    // =========================================================================
    // Multiple Key Operations
    // =========================================================================

    /**
     * Get multiple values from the cache
     *
     * @param array $keys Array of cache keys
     * @param mixed $default Default value for missing keys
     * @return array Associative array of key => value
     */
    public function getMultiple(array $keys, mixed $default = null): array;

    /**
     * Store multiple values in the cache
     *
     * @param array $values Associative array of key => value
     * @param int|null $ttl Time to live in seconds
     * @return bool True on success, false on failure
     */
    public function setMultiple(array $values, ?int $ttl = null): bool;

    /**
     * Delete multiple values from the cache
     *
     * @param array $keys Array of cache keys
     * @return bool True on success, false on failure
     */
    public function deleteMultiple(array $keys): bool;

    // =========================================================================
    // Atomic Operations
    // =========================================================================

    /**
     * Increment a numeric value in the cache
     *
     * @param string $key The cache key
     * @param int $value The amount to increment by (default: 1)
     * @return int|false The new value or false on failure
     */
    public function increment(string $key, int $value = 1): int|false;

    /**
     * Decrement a numeric value in the cache
     *
     * @param string $key The cache key
     * @param int $value The amount to decrement by (default: 1)
     * @return int|false The new value or false on failure
     */
    public function decrement(string $key, int $value = 1): int|false;

    // =========================================================================
    // TTL Operations
    // =========================================================================

    /**
     * Get the remaining time to live for a cache key
     *
     * @param string $key The cache key
     * @return int|null Seconds remaining or null if expired/doesn't exist
     */
    public function getTtl(string $key): ?int;

    /**
     * Set/update the time to live for a cache key
     *
     * @param string $key The cache key
     * @param int $ttl Time to live in seconds
     * @return bool True on success, false on failure
     */
    public function setTtl(string $key, int $ttl): bool;

    // =========================================================================
    // Utility Methods
    // =========================================================================

    /**
     * Flush all cache (alias for clear)
     *
     * @return bool True on success, false on failure
     */
    public function flush(): bool;

    /**
     * Get a value from cache, or execute callback and store result
     *
     * This is the "cache aside" pattern - if the key doesn't exist,
     * execute the callback, store the result, and return it.
     *
     * @param string $key The cache key
     * @param int $ttl Time to live in seconds
     * @param callable $callback Function to execute if cache miss
     * @return mixed The cached or computed value
     */
    public function remember(string $key, int $ttl, callable $callback): mixed;

    /**
     * Get a value from cache, or execute callback and store forever
     *
     * @param string $key The cache key
     * @param callable $callback Function to execute if cache miss
     * @return mixed The cached or computed value
     */
    public function rememberForever(string $key, callable $callback): mixed;

    /**
     * Get a value and delete it from the cache
     *
     * Retrieves the value and immediately removes it from cache.
     * Useful for one-time tokens, flash messages, etc.
     *
     * @param string $key The cache key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The cached value or default
     */
    public function pull(string $key, mixed $default = null): mixed;

    /**
     * Store a value in the cache forever (no expiration)
     *
     * @param string $key The cache key
     * @param mixed $value The value to cache
     * @return bool True on success, false on failure
     */
    public function forever(string $key, mixed $value): bool;
}
