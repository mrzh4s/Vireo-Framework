<?php
/**
 * Cache Helper Functions
 *
 * Convenient functions for accessing the cache system throughout the application.
 */

use Vireo\Framework\Cache\Cache;
use Vireo\Framework\Cache\CacheInterface;

if (!function_exists('cache')) {
    /**
     * Get cache store instance or get/set cache value
     *
     * Usage:
     * - cache() - Get default cache store
     * - cache('redis') - Get specific cache store
     * - cache()->get('key') - Get value from default store
     * - cache('redis')->set('key', 'value', 3600) - Set value in Redis store
     *
     * @param string|null $store Store name (null = default)
     * @return CacheInterface
     */
    function cache(?string $store = null): CacheInterface
    {
        return Cache::store($store);
    }
}

if (!function_exists('cache_get')) {
    /**
     * Get a value from the cache
     *
     * @param string $key Cache key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    function cache_get(string $key, mixed $default = null): mixed
    {
        return cache()->get($key, $default);
    }
}

if (!function_exists('cache_set')) {
    /**
     * Store a value in the cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl Time to live in seconds (null = use default)
     * @return bool
     */
    function cache_set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return cache()->set($key, $value, $ttl);
    }
}

if (!function_exists('cache_has')) {
    /**
     * Check if a key exists in the cache
     *
     * @param string $key Cache key
     * @return bool
     */
    function cache_has(string $key): bool
    {
        return cache()->has($key);
    }
}

if (!function_exists('cache_forget')) {
    /**
     * Delete a value from the cache
     *
     * @param string $key Cache key
     * @return bool
     */
    function cache_forget(string $key): bool
    {
        return cache()->delete($key);
    }
}

if (!function_exists('cache_remember')) {
    /**
     * Get a value from cache, or execute callback and store result
     *
     * This is the "cache aside" pattern.
     *
     * @param string $key Cache key
     * @param int $ttl Time to live in seconds
     * @param callable $callback Function to execute on cache miss
     * @return mixed
     */
    function cache_remember(string $key, int $ttl, callable $callback): mixed
    {
        return cache()->remember($key, $ttl, $callback);
    }
}

if (!function_exists('cache_remember_forever')) {
    /**
     * Get a value from cache, or execute callback and store forever
     *
     * @param string $key Cache key
     * @param callable $callback Function to execute on cache miss
     * @return mixed
     */
    function cache_remember_forever(string $key, callable $callback): mixed
    {
        return cache()->rememberForever($key, $callback);
    }
}

if (!function_exists('cache_pull')) {
    /**
     * Get a value and delete it from the cache
     *
     * Useful for one-time tokens, flash messages, etc.
     *
     * @param string $key Cache key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    function cache_pull(string $key, mixed $default = null): mixed
    {
        return cache()->pull($key, $default);
    }
}

if (!function_exists('cache_forever')) {
    /**
     * Store a value in the cache forever (no expiration)
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @return bool
     */
    function cache_forever(string $key, mixed $value): bool
    {
        return cache()->forever($key, $value);
    }
}

if (!function_exists('cache_flush')) {
    /**
     * Clear all values from the cache
     *
     * @return bool
     */
    function cache_flush(): bool
    {
        return cache()->flush();
    }
}

if (!function_exists('cache_increment')) {
    /**
     * Increment a numeric value in the cache
     *
     * @param string $key Cache key
     * @param int $value Amount to increment by (default: 1)
     * @return int|false New value or false on failure
     */
    function cache_increment(string $key, int $value = 1): int|false
    {
        return cache()->increment($key, $value);
    }
}

if (!function_exists('cache_decrement')) {
    /**
     * Decrement a numeric value in the cache
     *
     * @param string $key Cache key
     * @param int $value Amount to decrement by (default: 1)
     * @return int|false New value or false on failure
     */
    function cache_decrement(string $key, int $value = 1): int|false
    {
        return cache()->decrement($key, $value);
    }
}

if (!function_exists('cache_get_multiple')) {
    /**
     * Get multiple values from the cache
     *
     * @param array $keys Array of cache keys
     * @param mixed $default Default value for missing keys
     * @return array Associative array of key => value pairs
     */
    function cache_get_multiple(array $keys, mixed $default = null): array
    {
        return cache()->getMultiple($keys, $default);
    }
}

if (!function_exists('cache_set_multiple')) {
    /**
     * Store multiple values in the cache
     *
     * @param array $values Associative array of key => value pairs
     * @param int|null $ttl Time to live in seconds (null = use default)
     * @return bool
     */
    function cache_set_multiple(array $values, ?int $ttl = null): bool
    {
        return cache()->setMultiple($values, $ttl);
    }
}

if (!function_exists('cache_delete_multiple')) {
    /**
     * Delete multiple values from the cache
     *
     * @param array $keys Array of cache keys
     * @return bool
     */
    function cache_delete_multiple(array $keys): bool
    {
        return cache()->deleteMultiple($keys);
    }
}

if (!function_exists('cache_tags')) {
    /**
     * Cache with tags (for future implementation)
     *
     * Note: Tag support depends on the cache driver.
     * File cache doesn't support tags efficiently.
     *
     * @param array $tags
     * @return CacheInterface
     */
    function cache_tags(array $tags): CacheInterface
    {
        // For now, return default cache
        // Future: Implement tag support for Redis
        return cache();
    }
}
