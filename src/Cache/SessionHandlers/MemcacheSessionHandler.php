<?php

namespace Vireo\Framework\Cache\SessionHandlers;

use SessionHandlerInterface;
use Memcache;
use Exception;

/**
 * MemcacheSessionHandler - Legacy Memcache session storage handler
 *
 * Stores PHP sessions in Memcache.
 * Requires: php-memcache extension
 */
class MemcacheSessionHandler implements SessionHandlerInterface
{
    /**
     * Memcache connection
     */
    private Memcache $memcache;

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
     * @throws Exception If Memcache extension not loaded or connection fails
     */
    public function __construct(array $config)
    {
        if (!extension_loaded('memcache')) {
            throw new Exception('Memcache extension not loaded');
        }

        $this->memcache = new Memcache();
        $this->prefix = $config['prefix'] ?? 'pop_session:';
        $this->ttl = $config['lifetime'] ?? 7200; // 2 hours

        $servers = $config['servers'] ?? [
            ['host' => '127.0.0.1', 'port' => 11211]
        ];

        $connected = false;
        foreach ($servers as $server) {
            if ($this->memcache->pconnect($server['host'], $server['port'] ?? 11211)) {
                $connected = true;
            }
        }

        if (!$connected) {
            throw new Exception("Failed to connect to any Memcache servers");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function open(string $path, string $name): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $id): string|false
    {
        try {
            $data = $this->memcache->get($this->prefix . $id);
            return $data === false ? '' : $data;
        } catch (Exception $e) {
            error_log("MemcacheSessionHandler read error: " . $e->getMessage());
            return '';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $id, string $data): bool
    {
        try {
            return $this->memcache->set($this->prefix . $id, $data, MEMCACHE_COMPRESSED, $this->ttl);
        } catch (Exception $e) {
            error_log("MemcacheSessionHandler write error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(string $id): bool
    {
        try {
            return $this->memcache->delete($this->prefix . $id);
        } catch (Exception $e) {
            error_log("MemcacheSessionHandler destroy error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function gc(int $max_lifetime): int|false
    {
        // Memcache handles TTL automatically
        return 0;
    }
}
