<?php

namespace Framework\Cache;

/**
 * Cache - Static facade for cache access
 *
 * Provides a clean, static interface to the cache system.
 * All calls are delegated to CacheManager singleton.
 *
 * @method static CacheInterface store(?string $name = null)
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool set(string $key, mixed $value, ?int $ttl = null)
 * @method static bool has(string $key)
 * @method static bool delete(string $key)
 * @method static bool clear()
 * @method static array getMultiple(array $keys, mixed $default = null)
 * @method static bool setMultiple(array $values, ?int $ttl = null)
 * @method static bool deleteMultiple(array $keys)
 * @method static int|false increment(string $key, int $value = 1)
 * @method static int|false decrement(string $key, int $value = 1)
 * @method static int|null getTtl(string $key)
 * @method static bool setTtl(string $key, int $ttl)
 * @method static bool flush()
 * @method static mixed remember(string $key, int $ttl, callable $callback)
 * @method static mixed rememberForever(string $key, callable $callback)
 * @method static mixed pull(string $key, mixed $default = null)
 * @method static bool forever(string $key, mixed $value)
 */
class Cache
{
    /**
     * Magic method to forward static calls to CacheManager
     *
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        $manager = CacheManager::getInstance();
        return $manager->$method(...$arguments);
    }
}
