<?php

namespace Framework\Cache\SessionHandlers;

use SessionHandlerInterface;
use Memcached;
use Exception;

/**
 * MemcachedSessionHandler - Memcached session storage handler
 *
 * Stores PHP sessions in Memcached for distributed session management.
 * Requires: php-memcached extension
 */
class MemcachedSessionHandler implements SessionHandlerInterface
{
    /**
     * Memcached connection
     */
    private Memcached $memcached;

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
     * @throws Exception If Memcached extension not loaded or connection fails
     */
    public function __construct(array $config)
    {
        if (!extension_loaded('memcached')) {
            throw new Exception('Memcached extension not loaded');
        }

        $persistentId = $config['persistent_id'] ?? 'pop_session';
        $this->memcached = new Memcached($persistentId);

        $this->prefix = $config['prefix'] ?? 'pop_session:';
        $this->ttl = $config['lifetime'] ?? 7200; // 2 hours

        // Only add servers if not already connected
        if ($persistentId === null || count($this->memcached->getServerList()) === 0) {
            $servers = $config['servers'] ?? [
                ['host' => '127.0.0.1', 'port' => 11211, 'weight' => 100]
            ];

            foreach ($servers as $server) {
                $this->memcached->addServer(
                    $server['host'],
                    $server['port'] ?? 11211,
                    $server['weight'] ?? 100
                );
            }
        }

        // Set options
        $this->memcached->setOption(Memcached::OPT_COMPRESSION, true);
        $this->memcached->setOption(Memcached::OPT_BINARY_PROTOCOL, true);

        // Verify connection
        $version = $this->memcached->getVersion();
        if ($version === false || empty($version)) {
            throw new Exception("Failed to connect to Memcached servers");
        }
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
            $data = $this->memcached->get($this->prefix . $id);

            if ($data === false && $this->memcached->getResultCode() === Memcached::RES_NOTFOUND) {
                return '';
            }

            return $data === false ? '' : $data;
        } catch (Exception $e) {
            error_log("MemcachedSessionHandler read error: " . $e->getMessage());
            return '';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $id, string $data): bool
    {
        try {
            return $this->memcached->set($this->prefix . $id, $data, $this->ttl);
        } catch (Exception $e) {
            error_log("MemcachedSessionHandler write error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(string $id): bool
    {
        try {
            return $this->memcached->delete($this->prefix . $id);
        } catch (Exception $e) {
            error_log("MemcachedSessionHandler destroy error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function gc(int $max_lifetime): int|false
    {
        // Memcached handles TTL automatically, no garbage collection needed
        return 0;
    }
}
