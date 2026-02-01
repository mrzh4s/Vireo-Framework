<?php

namespace Vireo\Framework\Cache;

use Vireo\Framework\Cache\Drivers\FileCache;
use Vireo\Framework\Cache\Drivers\RedisCache;
use Vireo\Framework\Cache\Drivers\MemcachedCache;
use Vireo\Framework\Cache\Drivers\MemcacheCache;
use Vireo\Framework\Cache\Drivers\ArrayCache;
use Vireo\Framework\Cache\Drivers\DatabaseCache;
use Exception;

/**
 * CacheManager - Singleton cache manager
 *
 * Manages cache store instances and provides a factory for creating cache drivers.
 * Follows the same pattern as StorageManager for consistency.
 */
class CacheManager
{
    /**
     * Singleton instance
     */
    private static ?CacheManager $instance = null;

    /**
     * Cache configuration
     */
    private array $config;

    /**
     * Store instances (cached)
     */
    private array $stores = [];

    /**
     * Private constructor for singleton pattern
     */
    private function __construct()
    {
        $this->loadConfig();
    }

    /**
     * Get the singleton instance
     *
     * @return CacheManager
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Load cache configuration
     *
     * @return void
     */
    private function loadConfig(): void
    {
        $rootPath = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
        $configPath = $rootPath . '/Config/Cache.php';

        if (file_exists($configPath)) {
            $this->config = require $configPath;
        } else {
            // Default configuration if file doesn't exist
            $this->config = [
                'default' => 'file',
                'stores' => [
                    'file' => [
                        'driver' => 'file',
                        'path' => $rootPath . '/storage/cache',
                        'ttl' => 3600,
                    ],
                ],
            ];
        }
    }

    /**
     * Get a cache store instance
     *
     * @param string|null $name Store name (null = default store)
     * @return CacheInterface
     * @throws Exception If store not found or driver creation fails
     */
    public function store(?string $name = null): CacheInterface
    {
        // Use default store if no name provided
        if ($name === null) {
            $name = $this->config['default'] ?? 'file';
        }

        // Return cached instance if exists
        if (isset($this->stores[$name])) {
            return $this->stores[$name];
        }

        // Create new store instance
        $store = $this->createStore($name);
        $this->stores[$name] = $store;

        return $store;
    }

    /**
     * Create a cache store instance
     *
     * @param string $name Store name
     * @return CacheInterface
     * @throws Exception If store configuration not found or driver unsupported
     */
    private function createStore(string $name): CacheInterface
    {
        // Check if store configuration exists
        if (!isset($this->config['stores'][$name])) {
            throw new Exception("Cache store '{$name}' not found in configuration");
        }

        $config = $this->config['stores'][$name];
        $driver = $config['driver'] ?? 'file';

        try {
            // Create driver instance
            return match ($driver) {
                'file' => new FileCache($config),
                'redis' => new RedisCache($config),
                'memcached' => new MemcachedCache($config),
                'memcache' => new MemcacheCache($config),
                'array' => new ArrayCache($config),
                'database' => new DatabaseCache($config),
                default => throw new Exception("Unsupported cache driver: {$driver}"),
            };
        } catch (Exception $e) {
            // Log error and fallback to file cache
            error_log("Cache driver '{$driver}' failed: " . $e->getMessage() . ". Falling back to file driver.");

            // Return file cache as fallback
            $rootPath = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
            return new FileCache([
                'path' => $rootPath . '/storage/cache',
                'prefix' => $config['prefix'] ?? '',
                'ttl' => $config['ttl'] ?? 3600,
            ]);
        }
    }

    /**
     * Get all configured store names
     *
     * @return array
     */
    public function getStores(): array
    {
        return array_keys($this->config['stores'] ?? []);
    }

    /**
     * Get the default store name
     *
     * @return string
     */
    public function getDefaultStore(): string
    {
        return $this->config['default'] ?? 'file';
    }

    /**
     * Purge a store instance (removes from cache)
     *
     * Useful for testing or when you need to recreate a connection
     *
     * @param string $name Store name
     * @return void
     */
    public function purgeStore(string $name): void
    {
        unset($this->stores[$name]);
    }

    // =========================================================================
    // Proxy Methods to Default Store
    // =========================================================================

    /**
     * Get a value from the default cache store
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store()->get($key, $default);
    }

    /**
     * Store a value in the default cache store
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl
     * @return bool
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->store()->set($key, $value, $ttl);
    }

    /**
     * Check if key exists in default cache store
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->store()->has($key);
    }

    /**
     * Delete a key from default cache store
     *
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        return $this->store()->delete($key);
    }

    /**
     * Clear the default cache store
     *
     * @return bool
     */
    public function clear(): bool
    {
        return $this->store()->clear();
    }

    /**
     * Get multiple values from default cache store
     *
     * @param array $keys
     * @param mixed $default
     * @return array
     */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        return $this->store()->getMultiple($keys, $default);
    }

    /**
     * Set multiple values in default cache store
     *
     * @param array $values
     * @param int|null $ttl
     * @return bool
     */
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        return $this->store()->setMultiple($values, $ttl);
    }

    /**
     * Delete multiple keys from default cache store
     *
     * @param array $keys
     * @return bool
     */
    public function deleteMultiple(array $keys): bool
    {
        return $this->store()->deleteMultiple($keys);
    }

    /**
     * Increment a value in default cache store
     *
     * @param string $key
     * @param int $value
     * @return int|false
     */
    public function increment(string $key, int $value = 1): int|false
    {
        return $this->store()->increment($key, $value);
    }

    /**
     * Decrement a value in default cache store
     *
     * @param string $key
     * @param int $value
     * @return int|false
     */
    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->store()->decrement($key, $value);
    }

    /**
     * Get TTL for a key in default cache store
     *
     * @param string $key
     * @return int|null
     */
    public function getTtl(string $key): ?int
    {
        return $this->store()->getTtl($key);
    }

    /**
     * Set TTL for a key in default cache store
     *
     * @param string $key
     * @param int $ttl
     * @return bool
     */
    public function setTtl(string $key, int $ttl): bool
    {
        return $this->store()->setTtl($key, $ttl);
    }

    /**
     * Flush default cache store
     *
     * @return bool
     */
    public function flush(): bool
    {
        return $this->store()->flush();
    }

    /**
     * Remember pattern on default cache store
     *
     * @param string $key
     * @param int $ttl
     * @param callable $callback
     * @return mixed
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        return $this->store()->remember($key, $ttl, $callback);
    }

    /**
     * Remember forever pattern on default cache store
     *
     * @param string $key
     * @param callable $callback
     * @return mixed
     */
    public function rememberForever(string $key, callable $callback): mixed
    {
        return $this->store()->rememberForever($key, $callback);
    }

    /**
     * Pull a value from default cache store
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        return $this->store()->pull($key, $default);
    }

    /**
     * Store a value forever in default cache store
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->store()->forever($key, $value);
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
