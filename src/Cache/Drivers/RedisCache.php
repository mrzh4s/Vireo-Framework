<?php

namespace Framework\Cache\Drivers;

use Framework\Cache\CacheInterface;
use Redis;
use Exception;

/**
 * RedisCache - Redis cache driver
 *
 * High-performance in-memory cache using Redis.
 * Requires: php-redis extension
 *
 * Features:
 * - Persistent connections for performance
 * - Automatic serialization
 * - Atomic increment/decrement operations
 * - TTL support with automatic expiration
 */
class RedisCache implements CacheInterface
{
    /**
     * Redis connection
     */
    private ?Redis $connection = null;

    /**
     * Cache key prefix
     */
    private string $prefix;

    /**
     * Default TTL in seconds
     */
    private ?int $defaultTtl;

    /**
     * Redis configuration
     */
    private array $config;

    /**
     * Constructor
     *
     * @param array $config Configuration options
     * @throws Exception If Redis extension not loaded
     */
    public function __construct(array $config)
    {
        // Check if Redis extension is loaded
        if (!extension_loaded('redis')) {
            throw new Exception(
                'Redis extension not loaded. Install with: pecl install redis or apt-get install php-redis'
            );
        }

        $this->config = $config;
        $this->prefix = $config['prefix'] ?? 'pop_cache:';
        $this->defaultTtl = $config['ttl'] ?? 3600;

        $this->connect();
    }

    /**
     * Connect to Redis server
     *
     * @return void
     * @throws Exception If connection fails
     */
    private function connect(): void
    {
        $this->connection = new Redis();

        $host = $this->config['host'] ?? '127.0.0.1';
        $port = $this->config['port'] ?? 6379;
        $timeout = $this->config['timeout'] ?? 0.0;
        $persistent = $this->config['persistent'] ?? true;
        $persistentId = $this->config['persistent_id'] ?? 'pop_redis';

        // Use persistent connection for better performance
        if ($persistent) {
            $connected = $this->connection->pconnect($host, $port, $timeout, $persistentId);
        } else {
            $connected = $this->connection->connect($host, $port, $timeout);
        }

        if (!$connected) {
            throw new Exception("Failed to connect to Redis at {$host}:{$port}");
        }

        // Authenticate if password provided
        if (!empty($this->config['password'])) {
            $this->connection->auth($this->config['password']);
        }

        // Select database
        $database = $this->config['database'] ?? 0;
        $this->connection->select($database);

        // Set serialization mode (PHP serialization for complex types)
        $this->connection->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
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
            error_log("RedisCache get error for key '{$key}': " . $e->getMessage());
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

            if ($ttl === null) {
                // No expiration
                return $this->connection->set($this->getKey($key), $value);
            }

            // Set with expiration
            return $this->connection->setex($this->getKey($key), $ttl, $value);
        } catch (Exception $e) {
            error_log("RedisCache set error for key '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        try {
            return $this->connection->exists($this->getKey($key)) > 0;
        } catch (Exception $e) {
            error_log("RedisCache has error for key '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        try {
            return $this->connection->del($this->getKey($key)) > 0;
        } catch (Exception $e) {
            error_log("RedisCache delete error for key '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        try {
            // Only flush keys with our prefix
            $pattern = $this->prefix . '*';
            $keys = $this->connection->keys($pattern);

            if (empty($keys)) {
                return true;
            }

            return $this->connection->del($keys) > 0;
        } catch (Exception $e) {
            error_log("RedisCache clear error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        $values = [];

        try {
            $prefixedKeys = array_map(fn($key) => $this->getKey($key), $keys);
            $results = $this->connection->mGet($prefixedKeys);

            foreach ($keys as $index => $key) {
                $values[$key] = $results[$index] === false ? $default : $results[$index];
            }
        } catch (Exception $e) {
            error_log("RedisCache getMultiple error: " . $e->getMessage());
            // Return defaults for all keys
            foreach ($keys as $key) {
                $values[$key] = $default;
            }
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $success = true;

        try {
            if ($ttl === null) {
                // Use mSet for no expiration
                $prefixedValues = [];
                foreach ($values as $key => $value) {
                    $prefixedValues[$this->getKey($key)] = $value;
                }
                return $this->connection->mSet($prefixedValues);
            } else {
                // Set each with expiration
                foreach ($values as $key => $value) {
                    if (!$this->set($key, $value, $ttl)) {
                        $success = false;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("RedisCache setMultiple error: " . $e->getMessage());
            return false;
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $keys): bool
    {
        try {
            $prefixedKeys = array_map(fn($key) => $this->getKey($key), $keys);
            return $this->connection->del($prefixedKeys) > 0;
        } catch (Exception $e) {
            error_log("RedisCache deleteMultiple error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function increment(string $key, int $value = 1): int|false
    {
        try {
            return $this->connection->incrBy($this->getKey($key), $value);
        } catch (Exception $e) {
            error_log("RedisCache increment error for key '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function decrement(string $key, int $value = 1): int|false
    {
        try {
            return $this->connection->decrBy($this->getKey($key), $value);
        } catch (Exception $e) {
            error_log("RedisCache decrement error for key '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTtl(string $key): ?int
    {
        try {
            $ttl = $this->connection->ttl($this->getKey($key));

            // -2: key doesn't exist
            // -1: key exists but has no expiration
            // >= 0: seconds remaining
            if ($ttl === -2) {
                return null; // Key doesn't exist
            } elseif ($ttl === -1) {
                return null; // No expiration set
            } else {
                return $ttl;
            }
        } catch (Exception $e) {
            error_log("RedisCache getTtl error for key '{$key}': " . $e->getMessage());
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setTtl(string $key, int $ttl): bool
    {
        try {
            return $this->connection->expire($this->getKey($key), $ttl);
        } catch (Exception $e) {
            error_log("RedisCache setTtl error for key '{$key}': " . $e->getMessage());
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
        try {
            // Use Redis transaction for atomic get+delete
            $this->connection->watch($this->getKey($key));
            $value = $this->get($key, $default);

            $this->connection->multi();
            $this->connection->del($this->getKey($key));
            $this->connection->exec();

            return $value;
        } catch (Exception $e) {
            error_log("RedisCache pull error for key '{$key}': " . $e->getMessage());
            return $default;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->set($key, $value, null);
    }

    /**
     * Get Redis connection (for advanced operations)
     *
     * @return Redis
     */
    public function getConnection(): Redis
    {
        return $this->connection;
    }
}
