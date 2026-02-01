<?php

namespace Framework\Cache\Drivers;

use Framework\Cache\CacheInterface;
use PDO;
use Exception;

/**
 * DatabaseCache - Database cache driver
 *
 * Stores cache data in database table.
 * Requires: cache table migration
 */
class DatabaseCache implements CacheInterface
{
    /**
     * PDO connection
     */
    private PDO $pdo;

    /**
     * Cache table name
     */
    private string $table;

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
        $connection = $config['connection'] ?? 'app';
        $this->table = $config['table'] ?? 'cache';
        $this->prefix = $config['prefix'] ?? '';
        $this->defaultTtl = $config['ttl'] ?? 3600;

        // Get database connection using db() helper
        $this->pdo = db($connection);
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
            $stmt = $this->pdo->prepare(
                "SELECT value, expires_at FROM {$this->table} WHERE key = ? LIMIT 1"
            );

            $stmt->execute([$this->getKey($key)]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return $default;
            }

            // Check if expired
            if ($result['expires_at'] !== null && $result['expires_at'] < time()) {
                $this->delete($key);
                return $default;
            }

            return unserialize($result['value']);
        } catch (Exception $e) {
            error_log("DatabaseCache get error for key '{$key}': " . $e->getMessage());
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
            $expiresAt = $ttl !== null ? time() + $ttl : null;

            $cacheKey = $this->getKey($key);

            // Try update first
            $stmt = $this->pdo->prepare(
                "UPDATE {$this->table} SET value = ?, expires_at = ?, created_at = ? WHERE key = ?"
            );

            $stmt->execute([
                serialize($value),
                $expiresAt,
                date('Y-m-d H:i:s'),
                $cacheKey,
            ]);

            // If no rows updated, insert new
            if ($stmt->rowCount() === 0) {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO {$this->table} (key, value, expires_at, created_at) VALUES (?, ?, ?, ?)"
                );

                $stmt->execute([
                    $cacheKey,
                    serialize($value),
                    $expiresAt,
                    date('Y-m-d H:i:s'),
                ]);
            }

            return true;
        } catch (Exception $e) {
            error_log("DatabaseCache set error for key '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT 1 FROM {$this->table} WHERE key = ? AND (expires_at IS NULL OR expires_at > ?) LIMIT 1"
            );

            $stmt->execute([$this->getKey($key), time()]);

            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            error_log("DatabaseCache has error for key '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE key = ?");
            return $stmt->execute([$this->getKey($key)]);
        } catch (Exception $e) {
            error_log("DatabaseCache delete error for key '{$key}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table}");
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("DatabaseCache clear error: " . $e->getMessage());
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
        try {
            $stmt = $this->pdo->prepare(
                "SELECT expires_at FROM {$this->table} WHERE key = ? LIMIT 1"
            );

            $stmt->execute([$this->getKey($key)]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result || $result['expires_at'] === null) {
                return null;
            }

            $ttl = $result['expires_at'] - time();

            return $ttl > 0 ? $ttl : null;
        } catch (Exception $e) {
            error_log("DatabaseCache getTtl error for key '{$key}': " . $e->getMessage());
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setTtl(string $key, int $ttl): bool
    {
        try {
            $expiresAt = time() + $ttl;

            $stmt = $this->pdo->prepare(
                "UPDATE {$this->table} SET expires_at = ? WHERE key = ?"
            );

            return $stmt->execute([$expiresAt, $this->getKey($key)]);
        } catch (Exception $e) {
            error_log("DatabaseCache setTtl error for key '{$key}': " . $e->getMessage());
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
}
