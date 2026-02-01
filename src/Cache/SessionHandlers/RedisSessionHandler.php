<?php

namespace Framework\Cache\SessionHandlers;

use SessionHandlerInterface;
use Redis;
use Exception;

/**
 * RedisSessionHandler - Redis session storage handler
 *
 * Stores PHP sessions in Redis for distributed/scalable session management.
 * Requires: php-redis extension
 */
class RedisSessionHandler implements SessionHandlerInterface
{
    /**
     * Redis connection
     */
    private Redis $redis;

    /**
     * Session key prefix
     */
    private string $prefix;

    /**
     * Session lifetime in seconds
     */
    private int $ttl;

    /**
     * Constructor
     *
     * @param array $config Configuration options
     * @throws Exception If Redis extension not loaded or connection fails
     */
    public function __construct(array $config)
    {
        if (!extension_loaded('redis')) {
            throw new Exception('Redis extension not loaded');
        }

        $this->redis = new Redis();
        $this->prefix = $config['prefix'] ?? 'pop_session:';
        $this->ttl = $config['lifetime'] ?? 7200; // 2 hours

        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 6379;
        $persistent = $config['persistent'] ?? true;
        $persistentId = $config['persistent_id'] ?? 'pop_session';

        // Connect to Redis
        if ($persistent) {
            $connected = $this->redis->pconnect($host, $port, 0.0, $persistentId);
        } else {
            $connected = $this->redis->connect($host, $port);
        }

        if (!$connected) {
            throw new Exception("Failed to connect to Redis at {$host}:{$port}");
        }

        // Authenticate if password provided
        if (!empty($config['password'])) {
            $this->redis->auth($config['password']);
        }

        // Select database
        $database = $config['database'] ?? 1; // Use database 1 for sessions (0 for cache)
        $this->redis->select($database);
    }

    /**
     * {@inheritdoc}
     */
    public function open(string $path, string $name): bool
    {
        // Connection already established in constructor
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): bool
    {
        // Don't close persistent connections
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $id): string|false
    {
        try {
            $data = $this->redis->get($this->prefix . $id);
            return $data === false ? '' : $data;
        } catch (Exception $e) {
            error_log("RedisSessionHandler read error: " . $e->getMessage());
            return '';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $id, string $data): bool
    {
        try {
            return $this->redis->setex($this->prefix . $id, $this->ttl, $data);
        } catch (Exception $e) {
            error_log("RedisSessionHandler write error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(string $id): bool
    {
        try {
            return $this->redis->del($this->prefix . $id) > 0;
        } catch (Exception $e) {
            error_log("RedisSessionHandler destroy error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function gc(int $max_lifetime): int|false
    {
        // Redis handles TTL automatically, no garbage collection needed
        return 0;
    }
}
