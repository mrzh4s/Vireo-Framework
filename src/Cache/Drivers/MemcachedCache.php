<?php

namespace Vireo\Framework\Cache\Drivers;

use Vireo\Framework\Cache\CacheInterface;
use Memcached;
use Exception;

/**
 * MemcachedCache - Memcached cache driver
 *
 * Modern Memcached driver using php-memcached extension (libmemcached-based).
 * Requires: php-memcached extension
 *
 * Features:
 * - Persistent connections
 * - Multiple server support
 * - Binary protocol for performance
 * - Compression
 * - Automatic serialization
 */
class MemcachedCache implements CacheInterface
{
    /**
     * Memcached connection
     */
    private ?Memcached $connection = null;

    /**
     * Cache key prefix
     */
    private string $prefix;

    /**
     * Default TTL in seconds
     */
    private ?int $defaultTtl;

    /**
     * Memcached configuration
     */
    private array $config;

    /**
     * Constructor
     *
     * @param array $config Configuration options
     * @throws Exception If Memcached extension not loaded
     */
    public function __construct(array $config)
    {
        // Check if Memcached extension is loaded
        if (!extension_loaded('memcached')) {
            throw new Exception(
                'Memcached extension not loaded. Install with: pecl install memcached or apt-get install php-memcached'
            );
        }

        $this->config = $config;
        $this->prefix = $config['prefix'] ?? 'pop_cache:';
        $this->defaultTtl = $config['ttl'] ?? 3600;

        $this->connect();
    }

    /**
     * Connect to Memcached server(s)
     *
     * @return void
     * @throws Exception If connection fails
     */
    private function connect(): void
    {
        $persistentId = $this->config['persistent_id'] ?? 'pop_memcached';
        $this->connection = new Memcached($persistentId);

        // Only add servers if not using persistent connection or connection is empty
        if ($persistentId === null || count($this->connection->getServerList()) === 0) {
            $servers = $this->config['servers'] ?? [
                ['host' => '127.0.0.1', 'port' => 11211, 'weight' => 100]
            ];

            foreach ($servers as $server) {
                $this->connection->addServer(
                    $server['host'],
                    $server['port'] ?? 11211,
                    $server['weight'] ?? 100
                );
            }
        }

        // Set options for performance
        $this->connection->setOption(Memcached::OPT_COMPRESSION, true);
        $this->connection->setOption(Memcached::OPT_SERIALIZER, Memcached::SERIALIZER_PHP);
        $this->connection->setOption(Memcached::OPT_BINARY_PROTOCOL, true);

        // Set prefix using libmemcached prefix key option
        if (!empty($this->prefix)) {
            $this->connection->setOption(Memcached::OPT_PREFIX_KEY, $this->prefix);
        }

        // Verify connection
        $version = $this->connection->getVersion();
        if ($version === false || empty($version)) {
            throw new Exception("Failed to connect to Memcached servers");
        }
    }

    /**
     * Get full cache key (prefix handled by Memcached::OPT_PREFIX_KEY)
     *
     * @param string $key
     * @return string
     */
    private function getKey(string $key): string
    {
        // Prefix is already handled by Memcached::OPT_PREFIX_KEY
        return $key;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        try {
            $value = $this->connection->get($this->getKey($key));

            // Memcached returns false on miss, check result code
            if ($value === false && $this->connection->getResultCode() === Memcached::RES_NOTFOUND) {
                return $default;
            }

            return $value;
        } catch (Exception $e) {
            error_log("MemcachedCache get error for key '{$key}': " . $e->getMessage());
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

            // Memcached uses 0 for no expiration
            $expiration = $ttl === null ? 0 : $ttl;

            return $this->connection->set($this->getKey($key), $value, $expiration);
        } catch (Exception $e) {
            error_log("MemcachedCache set error for key '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        try {
            $this->connection->get($this->getKey($key));
            return $this->connection->getResultCode() !== Memcached::RES_NOTFOUND;
        } catch (Exception $e) {
            error_log("MemcachedCache has error for key '{$key}': " . $e->getMessage());
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
            error_log("MemcachedCache delete error for key '{$key}': " . $e->getMessage());
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
            error_log("MemcachedCache clear error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        try {
            $prefixedKeys = array_map(fn($key) => $this->getKey($key), $keys);
            $results = $this->connection->getMulti($prefixedKeys);

            if ($results === false) {
                // Return defaults for all keys
                return array_fill_keys($keys, $default);
            }

            // Fill in missing keys with default
            $values = [];
            foreach ($keys as $key) {
                $values[$key] = $results[$this->getKey($key)] ?? $default;
            }

            return $values;
        } catch (Exception $e) {
            error_log("MemcachedCache getMultiple error: " . $e->getMessage());
            return array_fill_keys($keys, $default);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        try {
            $ttl = $ttl ?? $this->defaultTtl;
            $expiration = $ttl === null ? 0 : $ttl;

            $prefixedValues = [];
            foreach ($values as $key => $value) {
                $prefixedValues[$this->getKey($key)] = $value;
            }

            return $this->connection->setMulti($prefixedValues, $expiration);
        } catch (Exception $e) {
            error_log("MemcachedCache setMultiple error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $keys): bool
    {
        try {
            $prefixedKeys = array_map(fn($key) => $this->getKey($key), $keys);
            $results = $this->connection->deleteMulti($prefixedKeys);

            // deleteMulti returns array of result codes
            return !in_array(false, $results, true);
        } catch (Exception $e) {
            error_log("MemcachedCache deleteMultiple error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function increment(string $key, int $value = 1): int|false
    {
        try {
            $result = $this->connection->increment($this->getKey($key), $value);

            // If key doesn't exist, create it with initial value
            if ($result === false) {
                if ($this->connection->add($this->getKey($key), $value, 0)) {
                    return $value;
                }
                return false;
            }

            return $result;
        } catch (Exception $e) {
            error_log("MemcachedCache increment error for key '{$key}': " . $e->getMessage());
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

            // If key doesn't exist, memcached won't go below 0
            if ($result === false) {
                return false;
            }

            return $result;
        } catch (Exception $e) {
            error_log("MemcachedCache decrement error for key '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTtl(string $key): ?int
    {
        // Memcached doesn't support getting TTL directly
        // This is a limitation of the protocol
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
            error_log("MemcachedCache setTtl error for key '{$key}': " . $e->getMessage());
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
     * Get Memcached connection (for advanced operations)
     *
     * @return Memcached
     */
    public function getConnection(): Memcached
    {
        return $this->connection;
    }
}
