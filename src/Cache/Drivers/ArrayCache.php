<?php

namespace Framework\Cache\Drivers;

use Framework\Cache\CacheInterface;

/**
 * ArrayCache - In-memory cache driver
 *
 * Stores cache data in a PHP array (in-memory, per-request).
 * Data is lost when the request ends. Useful for testing and temporary caching.
 */
class ArrayCache implements CacheInterface
{
    /**
     * Cache storage
     */
    private array $cache = [];

    /**
     * Cache key prefix
     */
    private string $prefix;

    /**
     * Constructor
     *
     * @param array $config Configuration options
     */
    public function __construct(array $config)
    {
        $this->prefix = $config['prefix'] ?? '';
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
        $cacheKey = $this->getKey($key);

        if (!isset($this->cache[$cacheKey])) {
            return $default;
        }

        $data = $this->cache[$cacheKey];

        // Check if expired
        if ($data['expires_at'] !== null && $data['expires_at'] < time()) {
            unset($this->cache[$cacheKey]);
            return $default;
        }

        return $data['value'];
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $cacheKey = $this->getKey($key);
        $expiresAt = $ttl !== null ? time() + $ttl : null;

        $this->cache[$cacheKey] = [
            'value' => $value,
            'expires_at' => $expiresAt,
        ];

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        $cacheKey = $this->getKey($key);

        if (!isset($this->cache[$cacheKey])) {
            return false;
        }

        $data = $this->cache[$cacheKey];

        // Check if expired
        if ($data['expires_at'] !== null && $data['expires_at'] < time()) {
            unset($this->cache[$cacheKey]);
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        $cacheKey = $this->getKey($key);
        unset($this->cache[$cacheKey]);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $this->cache = [];
        return true;
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
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function increment(string $key, int $value = 1): int|false
    {
        $current = (int) $this->get($key, 0);
        $new = $current + $value;

        $this->set($key, $new);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function decrement(string $key, int $value = 1): int|false
    {
        $current = (int) $this->get($key, 0);
        $new = $current - $value;

        $this->set($key, $new);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getTtl(string $key): ?int
    {
        $cacheKey = $this->getKey($key);

        if (!isset($this->cache[$cacheKey])) {
            return null;
        }

        $data = $this->cache[$cacheKey];

        if ($data['expires_at'] === null) {
            return null; // No expiration
        }

        $ttl = $data['expires_at'] - time();

        return $ttl > 0 ? $ttl : null;
    }

    /**
     * {@inheritdoc}
     */
    public function setTtl(string $key, int $ttl): bool
    {
        $value = $this->get($key);

        if ($value === null) {
            return false;
        }

        return $this->set($key, $value, $ttl);
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
}
