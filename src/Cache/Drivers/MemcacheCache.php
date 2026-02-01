<?php

namespace Vireo\Framework\Cache\Drivers;

use Vireo\Framework\Cache\CacheInterface;
use Memcache;
use Exception;

/**
 * MemcacheCache - Legacy Memcache cache driver
 *
 * Legacy Memcache driver using php-memcache extension.
 * Requires: php-memcache extension (older, fewer features than memcached)
 *
 * Note: Consider using MemcachedCache (php-memcached) for better performance and features.
 */
class MemcacheCache implements CacheInterface
{
    /**
     * Memcache connection
     */
    private ?Memcache $connection = null;

    /**
     * Cache key prefix
     */
    private string $prefix;

    /**
     * Default TTL in seconds
     */
    private ?int $defaultTtl;

    /**
     * Memcache configuration
     */
    private array $config;

    /**
     * Constructor
     *
     * @param array $config Configuration options
     * @throws Exception If Memcache extension not loaded
     */
    public function __construct(array $config)
    {
        // Check if Memcache extension is loaded
        if (!extension_loaded('memcache')) {
            throw new Exception(
                'Memcache extension not loaded. Install with: pecl install memcache or apt-get install php-memcache'
            );
        }

        $this->config = $config;
        $this->prefix = $config['prefix'] ?? 'pop_cache:';
        $this->defaultTtl = $config['ttl'] ?? 3600;

        $this->connect();
    }

    /**
     * Connect to Memcache server(s)
     *
     * @return void
     * @throws Exception If connection fails
     */
    private function connect(): void
    {
        $this->connection = new Memcache();

        $servers = $this->config['servers'] ?? [
            ['host' => '127.0.0.1', 'port' => 11211]
        ];

        $connected = false;
        foreach ($servers as $server) {
            $host = $server['host'];
            $port = $server['port'] ?? 11211;

            // Use pconnect for persistent connections
            if ($this->connection->pconnect($host, $port)) {
                $connected = true;
            }
        }

        if (!$connected) {
            throw new Exception("Failed to connect to any Memcache servers");
        }
    }

    /**
     * Get full cache key with prefix
     *
     * @param string $key
     * @return string
     */
    private function getKey(string $key): string
    {
        return $this->prefix . $key;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        try {
            $value = $this->connection->get($this->getKey($key));
            return $value === false ? $default : $value;
        } catch (Exception $e) {
            error_log("MemcacheCache get error for key '{$key}': " . $e->getMessage());
            return $default;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        try {
            $ttl = $ttl ?? $this->defaultTtl;

            // Memcache uses 0 for no expiration
            $expiration = $ttl === null ? 0 : $ttl;

            // MEMCACHE_COMPRESSED flag for compression
            return $this->connection->set($this->getKey($key), $value, MEMCACHE_COMPRESSED, $expiration);
        } catch (Exception $e) {
            error_log("MemcacheCache set error for key '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        try {
            return $this->connection->get($this->getKey($key)) !== false;
        } catch (Exception $e) {
            error_log("MemcacheCache has error for key '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        try {
            return $this->connection->delete($this->getKey($key));
        } catch (Exception $e) {
            error_log("MemcacheCache delete error for key '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        try {
            return $this->connection->flush();
        } catch (Exception $e) {
            error_log("MemcacheCache clear error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        $success = true;

        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $keys): bool
    {
        $success = true;

        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function increment(string $key, int $value = 1): int|false
    {
        try {
            $result = $this->connection->increment($this->getKey($key), $value);

            // If key doesn't exist, set it with initial value
            if ($result === false) {
                if ($this->set($key, $value)) {
                    return $value;
                }
                return false;
            }

            return $result;
        } catch (Exception $e) {
            error_log("MemcacheCache increment error for key '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function decrement(string $key, int $value = 1): int|false
    {
        try {
            $result = $this->connection->decrement($this->getKey($key), $value);

            if ($result === false) {
                return false;
            }

            return $result;
        } catch (Exception $e) {
            error_log("MemcacheCache decrement error for key '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTtl(string $key): ?int
    {
        // Memcache doesn't support getting TTL
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function setTtl(string $key, int $ttl): bool
    {
        try {
            // Get current value
            $value = $this->get($key);

            if ($value === null) {
                return false;
            }

            // Re-set with new TTL
            return $this->set($key, $value, $ttl);
        } catch (Exception $e) {
            error_log("MemcacheCache setTtl error for key '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): bool
    {
        return $this->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function rememberForever(string $key, callable $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->forever($key, $value);

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->delete($key);

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->set($key, $value, null);
    }

    /**
     * Get Memcache connection (for advanced operations)
     *
     * @return Memcache
     */
    public function getConnection(): Memcache
    {
        return $this->connection;
    }
}
