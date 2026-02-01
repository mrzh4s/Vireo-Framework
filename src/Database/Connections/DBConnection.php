<?php

namespace Framework\Database\Connections;
use Exception;
use PDO;
use PDOException;

class DBConnection
{
    private static $instance = null;
    private $connections = [];
    private $config = [];
    private $connectionAttempts = [];
    private $lastConnectionTime = [];
    private $connectionStats = [];

    public function __construct()
    {
        $this->loadConfiguration();
    }

    /**
     * Load database configuration from config/Database.php
     */
    private function loadConfiguration()
    {
        $configPath = ROOT_PATH . '/Config/Database.php';

        if (file_exists($configPath)) {
            $this->config = require $configPath;
        }

        // Initialize connection slots for all defined connections
        if (isset($this->config['connections'])) {
            foreach (array_keys($this->config['connections']) as $name) {
                $this->connections[$name] = null;
            }
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get connection by name
     * If no name provided, uses default connection
     */
    public function getConnection($name = null)
    {
        // Use default connection if no name provided
        if ($name === null) {
            $name = $this->config['default'] ?? 'main';
        }

        // Check if connection is defined
        if (!isset($this->config['connections'][$name])) {
            $availableConnections = implode(', ', array_keys($this->config['connections']));
            throw new Exception("Connection '{$name}' not found. Available connections: {$availableConnections}");
        }

        // Return existing connection if alive
        if (isset($this->connections[$name]) && $this->isConnectionAlive($this->connections[$name], $name)) {
            return $this->connections[$name];
        }

        // Create new connection
        return $this->createConnection($name);
    }

    /**
     * Create connection based on driver type
     */
    private function createConnection($name)
    {
        $config = $this->config['connections'][$name];
        $driver = $config['driver'] ?? 'sqlite';

        switch ($driver) {
            case 'sqlite':
                return $this->createSQLiteConnection($name, $config);

            case 'pgsql':
            case 'postgres':
            case 'postgresql':
                return $this->createPostgreSQLConnection($name, $config);

            case 'mysql':
                return $this->createMySQLConnection($name, $config);

            case 'sqlsrv':
            case 'mssql':
                return $this->createSQLServerConnection($name, $config);

            default:
                throw new Exception("Unsupported database driver: {$driver}");
        }
    }

    /**
     * Create PostgreSQL connection with retry logic
     */
    private function createPostgreSQLConnection($name, $config)
    {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 5432;
        $database = $config['database'] ?? '';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $charset = $config['charset'] ?? 'utf8';
        $schema = $config['schema'] ?? 'public';

        if (!$host || !$database) {
            throw new Exception("Invalid PostgreSQL configuration for '{$name}'");
        }

        $dsn = "pgsql:host={$host};port={$port};dbname={$database}";

        $poolConfig = $this->config['pool'] ?? [];
        $maxRetries = $poolConfig['max_retries'] ?? 3;
        $baseTimeout = $poolConfig['base_timeout'] ?? 10;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $start = microtime(true);
            $timeout = $baseTimeout + ($attempt * 5);

            try {
                $options = $this->buildPDOOptions($config, [
                    PDO::ATTR_TIMEOUT => $timeout,
                    PDO::ATTR_PERSISTENT => false,
                ]);

                $pdo = new PDO($dsn, $username, $password, $options);

                // Ensure error mode is set to EXCEPTION
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Set charset AFTER connection
                $pdo->exec("SET client_encoding = '{$charset}'");

                // Apply custom PostgreSQL options
                if (isset($config['options'])) {
                    foreach ($config['options'] as $key => $value) {
                        $pdo->exec("SET {$key} = '{$value}'");
                    }
                }

                // Set schema if specified
                if ($schema) {
                    $pdo->exec("SET search_path TO {$schema}");
                }

                // Test connection
                $pdo->query("SELECT 1");

                $connectionTime = (microtime(true) - $start) * 1000;
                $this->recordConnection($name, $attempt, $connectionTime, true);

                $this->connections[$name] = $pdo;
                return $pdo;

            } catch (PDOException $e) {
                $failedTime = (microtime(true) - $start) * 1000;
                $this->recordConnection($name, $attempt, $failedTime, false, $e->getMessage());

                if ($attempt < $maxRetries) {
                    usleep(pow(2, $attempt) * 100000); // Exponential backoff
                } else {
                    throw new Exception("PostgreSQL connection '{$name}' failed after {$maxRetries} attempts: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Create MySQL connection
     */
    private function createMySQLConnection($name, $config)
    {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 3306;
        $database = $config['database'] ?? '';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';
        $collation = $config['collation'] ?? 'utf8mb4_unicode_ci';

        if (!$host || !$database) {
            throw new Exception("Invalid MySQL configuration for '{$name}'");
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        try {
            $options = $this->buildPDOOptions($config, [
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$collation}",
            ]);

            $pdo = new PDO($dsn, $username, $password, $options);

            // Apply MySQL-specific settings
            if (isset($config['strict']) && $config['strict']) {
                $pdo->exec("SET sql_mode='STRICT_ALL_TABLES'");
            }

            if (isset($config['timezone'])) {
                $pdo->exec("SET time_zone='{$config['timezone']}'");
            }

            // Test connection
            $pdo->query("SELECT 1");

            if (app_debug()) {
                logger('database')->debug("MySQL connection established", [
                    'connection' => $name,
                    'host' => $host,
                    'port' => $port,
                    'database' => $database
                ]);
            }

            $this->connections[$name] = $pdo;
            return $pdo;

        } catch (PDOException $e) {
            throw new Exception("MySQL connection '{$name}' failed: " . $e->getMessage());
        }
    }

    /**
     * Create SQL Server connection
     */
    private function createSQLServerConnection($name, $config)
    {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 1433;
        $database = $config['database'] ?? '';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';

        if (!$host || !$database) {
            throw new Exception("Invalid SQL Server configuration for '{$name}'");
        }

        $dsn = "sqlsrv:Server={$host},{$port};Database={$database}";

        try {
            $options = $this->buildPDOOptions($config);
            $pdo = new PDO($dsn, $username, $password, $options);

            // Test connection
            $pdo->query("SELECT 1");

            if (app_debug()) {
                logger('database')->debug("SQL Server connection established", [
                    'connection' => $name,
                    'host' => $host,
                    'port' => $port,
                    'database' => $database
                ]);
            }

            $this->connections[$name] = $pdo;
            return $pdo;

        } catch (PDOException $e) {
            throw new Exception("SQL Server connection '{$name}' failed: " . $e->getMessage());
        }
    }

    /**
     * Create SQLite connection
     */
    private function createSQLiteConnection($name, $config)
    {
        $dbPath = $config['database'] ?? 'database/app.db';

        // Convert relative path to absolute
        if (strpos($dbPath, '/') !== 0) {
            $dbPath = ROOT_PATH . '/' . ltrim($dbPath, '/');
        }

        // Create directory if not exists
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new Exception("Failed to create database directory: {$dir}");
            }
        }

        // Check if directory is writable
        if (!is_writable($dir)) {
            throw new Exception("Database directory is not writable: {$dir}. Current permissions: " . substr(sprintf('%o', fileperms($dir)), -4));
        }

        // If database file exists, check if it's writable
        if (file_exists($dbPath) && !is_writable($dbPath)) {
            throw new Exception("Database file exists but is not writable: {$dbPath}");
        }

        $dsn = "sqlite:{$dbPath}";

        try {
            $options = $this->buildPDOOptions($config);
            $pdo = new PDO($dsn, null, null, $options);

            // Enable foreign keys if specified
            if (isset($config['foreign_keys']) && $config['foreign_keys']) {
                $pdo->exec("PRAGMA foreign_keys = ON");
            }

            // Apply SQLite-specific optimizations
            if (isset($config['options'])) {
                foreach ($config['options'] as $key => $value) {
                    $pdo->exec("PRAGMA {$key} = {$value}");
                }
            }

            // Test connection
            $pdo->query("SELECT 1");

            if (app_debug()) {
                logger('database')->debug("SQLite connection established", [
                    'connection' => $name,
                    'path' => $dbPath
                ]);
            }

            $this->connections[$name] = $pdo;
            return $pdo;

        } catch (PDOException $e) {
            logger('database')->error("SQLite connection failed", [
                'connection' => $name,
                'path' => $dbPath,
                'error' => $e->getMessage()
            ]);
            throw new Exception("Failed to connect to SQLite database '{$name}': " . $e->getMessage());
        }
    }

    /**
     * Build PDO options from config
     */
    private function buildPDOOptions($config, $defaults = [])
    {
        $globalOptions = $this->config['pdo_options'] ?? [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        $configOptions = $config['pdo_options'] ?? [];

        return array_merge($globalOptions, $defaults, $configOptions);
    }

    /**
     * Check if connection is alive
     */
    private function isConnectionAlive($pdo, $name = 'unknown')
    {
        if (!$pdo) {
            return false;
        }

        try {
            $start = microtime(true);
            $pdo->query("SELECT 1");
            $pingTime = (microtime(true) - $start) * 1000;

            $poolConfig = $this->config['pool'] ?? [];
            $pingTimeout = $poolConfig['ping_timeout'] ?? 1000;

            if ($pingTime > $pingTimeout) {
                if (app_debug()) {
                    logger('database')->warning("Connection ping too slow - reconnecting", [
                        'connection' => $name,
                        'ping_time' => round($pingTime, 2) . 'ms',
                        'timeout' => $pingTimeout . 'ms'
                    ]);
                }
                return false;
            }

            return true;
        } catch (Exception $e) {
            if (app_debug()) {
                logger('database')->error("Connection health check failed", [
                    'connection' => $name,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }

    /**
     * Record connection attempt statistics
     */
    private function recordConnection($name, $attempt, $time, $success, $error = null)
    {
        $this->lastConnectionTime[$name] = $time;

        if (!isset($this->connectionAttempts[$name])) {
            $this->connectionAttempts[$name] = 0;
        }
        if ($success) {
            $this->connectionAttempts[$name]++;
        }

        if (!isset($this->connectionStats[$name])) {
            $this->connectionStats[$name] = [];
        }

        $this->connectionStats[$name][] = [
            'attempt' => $attempt,
            'time' => round($time, 2),
            'success' => $success,
            'error' => $error,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        if ($success && $time > 200 && app_debug()) {
            logger('database')->warning("Slow database connection", [
                'connection' => $name,
                'time' => round($time, 2) . 'ms',
                'attempt' => $attempt
            ]);
        }

        if (!$success && app_debug()) {
            logger('database')->error("Database connection attempt failed", [
                'connection' => $name,
                'attempt' => $attempt,
                'error' => $error
            ]);
        }
    }

    /**
     * Get all available connection names
     */
    public function getAvailableConnections()
    {
        return array_keys($this->config['connections'] ?? []);
    }

    /**
     * Get connection configuration
     */
    public function getConnectionConfig($name)
    {
        return $this->config['connections'][$name] ?? null;
    }

    /**
     * Get connection statistics
     */
    public function getConnectionStats($name = null)
    {
        if ($name) {
            return $this->connectionStats[$name] ?? [];
        }
        return $this->connectionStats;
    }

    /**
     * Test all connections
     */
    public function testAllConnections()
    {
        $results = [];

        foreach ($this->getAvailableConnections() as $name) {
            try {
                $conn = $this->getConnection($name);
                $conn->query("SELECT 1");
                $results[$name] = [
                    'status' => 'connected',
                    'driver' => $this->config['connections'][$name]['driver'] ?? 'unknown',
                    'error' => null
                ];
            } catch (Exception $e) {
                $results[$name] = [
                    'status' => 'failed',
                    'driver' => $this->config['connections'][$name]['driver'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Reset specific connection
     */
    public function resetConnection($name = null)
    {
        if ($name === null) {
            $name = $this->config['default'] ?? 'main';
        }

        if (isset($this->connections[$name])) {
            $this->connections[$name] = null;
        }
    }

    /**
     * Reset all connections
     */
    public function resetAllConnections()
    {
        foreach (array_keys($this->connections) as $name) {
            $this->connections[$name] = null;
        }
    }
}