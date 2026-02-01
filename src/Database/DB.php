<?php

/**
 * Enhanced Database Factory with Dynamic Multi-Connection Support
 *
 * FEATURES:
 * - Auto-discovers connections from config/Database.php
 * - Support for unlimited named connections
 * - Multiple database drivers: SQLite, PostgreSQL, MySQL, SQL Server
 * - Connection pooling with health checks
 * - Retry logic with exponential backoff
 * - Backward compatible with legacy code
 */

namespace Vireo\Framework\Database;
use Vireo\Framework\Database\Connections\DBConnection;
use Exception;

// ============== ENHANCED DB HELPER CLASS ==============
class DB
{
    /**
     * Get database connection
     * If no name provided, uses default connection from config
     *
     * Usage:
     * DB::connection()              // Get default connection
     * DB::connection('main')        // Get main connection
     * DB::connection('analytics')   // Get analytics connection
     */
    public static function connection($name = null)
    {
        return DBConnection::getInstance()->getConnection($name);
    }

    /**
     * Execute query on specific connection
     *
     * Usage:
     * DB::query("SELECT * FROM users")                    // Use default connection
     * DB::query("SELECT * FROM users WHERE id = ?", [1])  // With params
     * DB::query("SELECT * FROM logs", [], 'analytics')    // Specific connection
     */
    public static function query($query, $params = [], $connection = null)
    {
        try {
            $pdo = self::connection($connection);
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (Exception $e) {
            $connName = $connection ?? 'default';
            error_log("DB query error on connection '{$connName}': " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get list of available connections
     */
    public static function connections()
    {
        return DBConnection::getInstance()->getAvailableConnections();
    }

    /**
     * Get connection configuration
     */
    public static function config($name = null)
    {
        return DBConnection::getInstance()->getConnectionConfig($name);
    }

    /**
     * Get connection statistics
     */
    public static function stats($name = null)
    {
        return DBConnection::getInstance()->getConnectionStats($name);
    }

    /**
     * Get health status of all connections
     */
    public static function health()
    {
        return DBConnection::getInstance()->testAllConnections();
    }

    /**
     * Reset specific connection
     */
    public static function reset($name = null)
    {
        return DBConnection::getInstance()->resetConnection($name);
    }

    /**
     * Reset all connections
     */
    public static function resetAll()
    {
        return DBConnection::getInstance()->resetAllConnections();
    }
}