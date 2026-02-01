<?php

namespace Vireo\Framework\Cache\SessionHandlers;

use SessionHandlerInterface;
use PDO;
use Exception;

/**
 * DatabaseSessionHandler - Database session storage handler
 *
 * Stores PHP sessions in database table.
 * Requires: sessions table migration
 */
class DatabaseSessionHandler implements SessionHandlerInterface
{
    /**
     * PDO connection
     */
    private PDO $pdo;

    /**
     * Sessions table name
     */
    private string $table;

    /**
     * Session lifetime in seconds
     */
    private int $ttl;

    /**
     * Constructor
     *
     * @param array $config Configuration options
     * @throws Exception If database connection fails
     */
    public function __construct(array $config)
    {
        $connection = $config['connection'] ?? 'app';
        $this->table = $config['table'] ?? 'sessions';
        $this->ttl = $config['lifetime'] ?? 7200; // 2 hours

        // Get database connection using db() helper
        $this->pdo = db($connection);
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
            $stmt = $this->pdo->prepare(
                "SELECT payload FROM {$this->table} WHERE session_id = :id AND last_activity > :expiration LIMIT 1"
            );

            $stmt->execute([
                'id' => $id,
                'expiration' => time() - $this->ttl,
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Handle JSON payload from existing table structure
            if ($result) {
                return is_string($result['payload']) ? $result['payload'] : json_encode($result['payload']);
            }

            return '';
        } catch (Exception $e) {
            error_log("DatabaseSessionHandler read error: " . $e->getMessage());
            return '';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $id, string $data): bool
    {
        try {
            $userId = $_SESSION['user']['id'] ?? null;
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            // Try to update first
            $stmt = $this->pdo->prepare(
                "UPDATE {$this->table}
                 SET payload = :payload,
                     last_activity = :last_activity,
                     user_id = :user_id,
                     ip_address = :ip_address,
                     user_agent = :user_agent
                 WHERE session_id = :id"
            );

            $stmt->execute([
                'payload' => json_encode($data),
                'last_activity' => time(),
                'user_id' => $userId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'id' => $id,
            ]);

            // If no rows updated, insert new session
            if ($stmt->rowCount() === 0) {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO {$this->table} (session_id, user_id, ip_address, user_agent, payload, last_activity, created_at)
                     VALUES (:id, :user_id, :ip_address, :user_agent, :payload, :last_activity, :created_at)"
                );

                $stmt->execute([
                    'id' => $id,
                    'user_id' => $userId,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'payload' => json_encode($data),
                    'last_activity' => time(),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            return true;
        } catch (Exception $e) {
            error_log("DatabaseSessionHandler write error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(string $id): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE session_id = :id");
            return $stmt->execute(['id' => $id]);
        } catch (Exception $e) {
            error_log("DatabaseSessionHandler destroy error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function gc(int $max_lifetime): int|false
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE last_activity < :expiration");
            $stmt->execute(['expiration' => time() - $max_lifetime]);

            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("DatabaseSessionHandler gc error: " . $e->getMessage());
            return false;
        }
    }
}
