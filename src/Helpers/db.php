<?php

use Vireo\Framework\Database\DB;
/**
 * Database Connection Helper Functions
 * File: apps/core/helpers/connection.php
 *
 * Enhanced for dynamic multi-connection support
 * Works with config/Database.php for unlimited connections
 */

// ============== MAIN DATABASE HELPERS ==============

/**
 * Get database connection
 * Supports any connection defined in config/Database.php
 *
 * Usage:
 * db()                  // Get default connection
 * db('main')            // Get main connection
 * db('analytics')       // Get analytics connection
 * db('cache')           // Get cache connection
 * db('your_custom_db')  // Any connection you defined
 *
 * @param string|null $name Connection name (null = default from config)
 * @return PDO Database connection
 */
if (!function_exists('db')) {
    function db($name = null) {
        return DB::connection($name);
    }
}

/**
 * Execute database query
 * Supports named connections and parameter binding
 *
 * Usage:
 * db_query("SELECT * FROM users")                        // Default connection
 * db_query("SELECT * FROM users WHERE id = ?", [1])     // With params
 * db_query("SELECT * FROM logs", [], 'analytics')       // Specific connection
 *
 * @param string $query SQL query
 * @param array $params Query parameters
 * @param string|null $connection Connection name
 * @return PDOStatement
 */
if (!function_exists('db_query')) {
    function db_query($query, $params = [], $connection = null) {
        return DB::query($query, $params, $connection);
    }
}

/**
 * Get list of all available database connections
 * Returns array of connection names from config
 *
 * Usage:
 * $connections = db_connections();
 * // Returns: ['main', 'source', 'dest', 'analytics', 'cache', ...]
 *
 * @return array Connection names
 */
if (!function_exists('db_connections')) {
    function db_connections() {
        return DB::connections();
    }
}

/**
 * Get configuration for a specific database connection
 * Useful for debugging or displaying connection info
 *
 * Usage:
 * $config = db_config('main');
 * echo $config['driver'];  // 'sqlite'
 *
 * @param string|null $name Connection name
 * @return array|null Connection configuration
 */
if (!function_exists('db_config')) {
    function db_config($name = null) {
        return DB::config($name);
    }
}

/**
 * Get database health status
 * Tests all configured database connections
 *
 * Usage:
 * $health = db_health();
 * foreach ($health as $name => $status) {
 *     echo "$name: {$status['status']}\n";
 * }
 *
 * @return array Health status for all connections
 */
if (!function_exists('db_health')) {
    function db_health() {
        return DB::health();
    }
}

/**
 * Get database connection statistics
 * Shows connection attempts, timing, errors, etc.
 *
 * Usage:
 * $stats = db_stats('main');      // Stats for specific connection
 * $allStats = db_stats();         // Stats for all connections
 *
 * @param string|null $name Connection name
 * @return array Connection statistics
 */
if (!function_exists('db_stats')) {
    function db_stats($name = null) {
        return DB::stats($name);
    }
}

/**
 * Reset database connection (force reconnect)
 * Useful when connection becomes stale or after long-running operations
 *
 * Usage:
 * db_reset('analytics');  // Reset specific connection
 * db_reset();            // Reset default connection
 *
 * @param string|null $name Connection name
 */
if (!function_exists('db_reset')) {
    function db_reset($name = null) {
        return DB::reset($name);
    }
}

/**
 * Reset all database connections
 * Forces all connections to reconnect on next use
 *
 * Usage:
 * db_reset_all();
 */
if (!function_exists('db_reset_all')) {
    function db_reset_all() {
        return DB::resetAll();
    }
}

/**
 * Check if a database connection exists in configuration
 *
 * Usage:
 * if (db_exists('analytics')) {
 *     $db = db('analytics');
 * }
 *
 * @param string $name Connection name
 * @return bool True if connection exists
 */
if (!function_exists('db_exists')) {
    function db_exists($name) {
        return in_array($name, DB::connections());
    }
}

/**
 * Get database driver type for a connection
 *
 * Usage:
 * $driver = db_driver('main');  // Returns: 'sqlite', 'pgsql', 'mysql', etc.
 *
 * @param string|null $name Connection name
 * @return string|null Driver name
 */
if (!function_exists('db_driver')) {
    function db_driver($name = null) {
        $config = DB::config($name);
        return $config['driver'] ?? null;
    }
}

/**
 * Quick database fetch - execute query and fetch all results
 *
 * Usage:
 * $users = db_fetch("SELECT * FROM users");
 * $logs = db_fetch("SELECT * FROM logs WHERE date > ?", ['2024-01-01'], 'analytics');
 *
 * @param string $query SQL query
 * @param array $params Query parameters
 * @param string|null $connection Connection name
 * @return array Results as objects
 */
if (!function_exists('db_fetch')) {
    function db_fetch($query, $params = [], $connection = null) {
        $stmt = DB::query($query, $params, $connection);
        return $stmt->fetchAll();
    }
}

/**
 * Quick database fetch one - execute query and fetch single result
 *
 * Usage:
 * $user = db_fetch_one("SELECT * FROM users WHERE id = ?", [1]);
 *
 * @param string $query SQL query
 * @param array $params Query parameters
 * @param string|null $connection Connection name
 * @return object|false Single result or false
 */
if (!function_exists('db_fetch_one')) {
    function db_fetch_one($query, $params = [], $connection = null) {
        $stmt = DB::query($query, $params, $connection);
        return $stmt->fetch();
    }
}

/**
 * Get database connection info (without sensitive data)
 * Safe to display or log
 *
 * Usage:
 * $info = db_info('main');
 * echo "Database: {$info['database']}, Driver: {$info['driver']}";
 *
 * @param string|null $name Connection name
 * @return array Safe connection info
 */
if (!function_exists('db_info')) {
    function db_info($name = null) {
        $config = DB::config($name);

        if (!$config) {
            return null;
        }

        // Remove sensitive data
        $safe = [
            'driver' => $config['driver'] ?? 'unknown',
            'database' => $config['database'] ?? null,
        ];

        // Add host/port for remote databases
        if (isset($config['host'])) {
            $safe['host'] = $config['host'];
            $safe['port'] = $config['port'] ?? null;
        }

        return $safe;
    }
}


// ============== LEGACY SQLITE HELPERS (for backward compatibility) ==============

/**
 * Get SQLite database path
 * @deprecated Use db_config('main')['database'] instead
 */
if (!function_exists('sqlite_config')) {
    function sqlite_config() {
        $config = DB::config('main');
        if ($config && $config['driver'] === 'sqlite') {
            return $config['database'];
        }
        return env('APP_DB', 'database/app.db');
    }
}

/**
 * Get source database config value
 * @deprecated Use db_config('source')[$key] instead
 */
if (!function_exists('source_db_config')) {
    function source_db_config($key) {
        $config = DB::config('source');

        $mapping = [
            'db_host' => 'host',
            'db_port' => 'port',
            'db_database' => 'database',
            'db_username' => 'username',
            'db_password' => 'password',
        ];

        $configKey = $mapping[$key] ?? $key;
        return $config[$configKey] ?? null;
    }
}

/**
 * Get destination database config value
 * @deprecated Use db_config('dest')[$key] instead
 */
if (!function_exists('dest_db_config')) {
    function dest_db_config($key) {
        $config = DB::config('dest');

        $mapping = [
            'db_host' => 'host',
            'db_port' => 'port',
            'db_database' => 'database',
            'db_username' => 'username',
            'db_password' => 'password',
        ];

        $configKey = $mapping[$key] ?? $key;
        return $config[$configKey] ?? null;
    }
}
