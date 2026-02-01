<?php

namespace Vireo\Framework\Logging\Handlers;

use Vireo\Framework\Database\DB;
use Exception;

/**
 * Database Handler
 *
 * Writes log messages to database table
 */
class DatabaseHandler
{
    private string $table;
    private ?string $connection;

    public function __construct(array $config)
    {
        $this->table = $config['table'] ?? 'logs';
        $this->connection = $config['connection'] ?? null;
        $this->ensureLogTable();
    }

    /**
     * Ensure log table exists
     */
    private function ensureLogTable(): void
    {
        try {
            $db = DB::connection($this->connection);

            // Check if table exists (SQLite/MySQL/PostgreSQL compatible)
            $driver = $db->getAttribute(\PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                $check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$this->table}'");
            } elseif ($driver === 'pgsql') {
                $check = $db->query("SELECT tablename FROM pg_tables WHERE tablename='{$this->table}'");
            } else {
                $check = $db->query("SHOW TABLES LIKE '{$this->table}'");
            }

            if (!$check->fetch()) {
                $this->createLogTable($db, $driver);
            }
        } catch (Exception $e) {
            error_log("Failed to ensure log table: " . $e->getMessage());
        }
    }

    /**
     * Create log table
     */
    private function createLogTable($db, string $driver): void
    {
        if ($driver === 'pgsql') {
            $sql = "CREATE TABLE {$this->table} (
                id SERIAL PRIMARY KEY,
                channel VARCHAR(255),
                level VARCHAR(20),
                message TEXT,
                context TEXT,
                extra TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
        } else {
            $sql = "CREATE TABLE {$this->table} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                channel VARCHAR(255),
                level VARCHAR(20),
                message TEXT,
                context TEXT,
                extra TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )";
        }

        $db->exec($sql);

        // Create indexes
        $db->exec("CREATE INDEX idx_logs_level ON {$this->table}(level)");
        $db->exec("CREATE INDEX idx_logs_channel ON {$this->table}(channel)");
        $db->exec("CREATE INDEX idx_logs_created_at ON {$this->table}(created_at)");
    }

    /**
     * Handle log record
     */
    public function handle(array $record): void
    {
        try {
            $stmt = DB::connection($this->connection)->prepare(
                "INSERT INTO {$this->table} (channel, level, message, context, extra, created_at)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $record['channel'],
                $record['level'],
                $record['message'],
                json_encode($record['context']),
                json_encode($record['extra']),
                $record['datetime']->format('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            error_log("DatabaseHandler failed: " . $e->getMessage());
        }
    }
}
