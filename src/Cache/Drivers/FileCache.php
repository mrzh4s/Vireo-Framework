<?php

namespace Vireo\Framework\Cache\Drivers;

use Vireo\Framework\Cache\CacheInterface;

/**
 * FileCache - File-based cache driver
 *
 * Stores cache data in files with directory sharding for performance.
 * No external dependencies - works out of the box.
 */
class FileCache implements CacheInterface
{
    /**
     * Cache directory path
     */
    private string $path;

    /**
     * Cache key prefix
     */
    private string $prefix;

    /**
     * Default TTL in seconds
     */
    private ?int $defaultTtl;

    /**
     * Constructor
     *
     * @param array $config Configuration options
     */
    public function __construct(array $config)
    {
        $rootPath = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 3);
        $this->path = rtrim($config['path'] ?? $rootPath . '/storage/cache', '/');
        $this->prefix = $config['prefix'] ?? '';
        $this->defaultTtl = $config['ttl'] ?? null;

        // Create cache directory if it doesn't exist
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    /**
     * Get the file path for a cache key
     *
     * Uses directory sharding (first 2 chars of hash) for performance
     *
     * @param string $key
     * @return string
     */
    private function getFilePath(string $key): string
    {
        $hash = md5($this->prefix . $key);
        $dir = $this->path . '/' . substr($hash, 0, 2);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir . '/' . $hash;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return $default;
        }

        try {
            $contents = file_get_contents($file);

            if ($contents === false) {
                return $default;
            }

            $data = unserialize($contents);

            // Check if expired
            if ($data['expires_at'] !== null && $data['expires_at'] < time()) {
                $this->delete($key);
                return $default;
            }

            return $data['value'];
        } catch (\Exception $e) {
            error_log("FileCache get error for key '{$key}': " . $e->getMessage());
            return $default;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $expiresAt = $ttl !== null ? time() + $ttl : null;

        $data = [
            'value' => $value,
            'expires_at' => $expiresAt,
        ];

        $file = $this->getFilePath($key);

        try {
            return file_put_contents($file, serialize($data), LOCK_EX) !== false;
        } catch (\Exception $e) {
            error_log("FileCache set error for key '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return false;
        }

        try {
            $contents = file_get_contents($file);
            if ($contents === false) {
                return false;
            }

            $data = unserialize($contents);

            // Check if expired
            if ($data['expires_at'] !== null && $data['expires_at'] < time()) {
                $this->delete($key);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return true;
        }

        try {
            return unlink($file);
        } catch (\Exception $e) {
            error_log("FileCache delete error for key '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        try {
            $this->recursiveDelete($this->path);
            // Recreate the cache directory if it doesn't exist
            if (!is_dir($this->path)) {
                mkdir($this->path, 0755, true);
            }
            return true;
        } catch (\Exception $e) {
            error_log("FileCache clear error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Recursively delete directory contents
     *
     * @param string $dir
     * @return void
     */
    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;

            if (is_dir($path)) {
                $this->recursiveDelete($path);
                rmdir($path);
            } else {
                unlink($path);
            }
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
        $current = (int) $this->get($key, 0);
        $new = $current + $value;

        if ($this->set($key, $new)) {
            return $new;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function decrement(string $key, int $value = 1): int|false
    {
        $current = (int) $this->get($key, 0);
        $new = $current - $value;

        if ($this->set($key, $new)) {
            return $new;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getTtl(string $key): ?int
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return null;
        }

        try {
            $contents = file_get_contents($file);
            if ($contents === false) {
                return null;
            }

            $data = unserialize($contents);

            if ($data['expires_at'] === null) {
                return null; // No expiration
            }

            $ttl = $data['expires_at'] - time();

            return $ttl > 0 ? $ttl : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setTtl(string $key, int $ttl): bool
    {
        // Get current value
        $value = $this->get($key);

        if ($value === null) {
            return false;
        }

        // Re-set with new TTL
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
