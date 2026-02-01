<?php

namespace Framework\Storage\Connections;
use Exception;

/**
 * Enhanced FTP Connection Factory with Multi-Connection Support
 *
 * FEATURES:
 * - Auto-discovers connections from Config/Ftp.php
 * - Support for unlimited named FTP connections
 * - Connection pooling with automatic retry
 * - SSL/TLS support
 * - Passive mode support
 * - Upload/download helpers
 * - Connection health checks
 */
class FTPConnection
{
    private static $instance = null;
    private $connections = [];
    private $config = null;
    private $stats = [];

    private function __construct()
    {
        $this->loadConfig();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load FTP configuration
     */
    private function loadConfig()
    {
        $configPath = ROOT_PATH . '/Config/Ftp.php';

        if (!file_exists($configPath)) {
            throw new Exception("FTP configuration file not found: {$configPath}");
        }

        $this->config = require $configPath;
    }

    /**
     * Get FTP connection (with pooling)
     *
     * @param string|null $name Connection name (null = default)
     * @return \FTP\Connection FTP connection object (PHP 8.1+)
     */
    public function getConnection(?string $name = null): \FTP\Connection
    {
        // Use default connection if no name provided
        if ($name === null) {
            $name = $this->config['default'] ?? 'default';
        }

        // Return existing connection if available and alive
        if (isset($this->connections[$name]) && $this->isConnectionAlive($name)) {
            return $this->connections[$name];
        }

        // Create new connection
        return $this->createConnection($name);
    }

    /**
     * Create a new FTP connection
     *
     * @param string $name Connection name
     * @return \FTP\Connection FTP connection object (PHP 8.1+)
     */
    private function createConnection(string $name): \FTP\Connection
    {
        $config = $this->getConnectionConfig($name);

        if (!$config) {
            throw new Exception("FTP connection '{$name}' not found in configuration");
        }

        $startTime = microtime(true);

        try {
            // Use SSL if configured
            if ($config['ssl'] ?? false) {
                $connection = @ftp_ssl_connect($config['host'], $config['port'] ?? 21, $config['timeout'] ?? 90);
            } else {
                $connection = @ftp_connect($config['host'], $config['port'] ?? 21, $config['timeout'] ?? 90);
            }

            if (!$connection) {
                throw new Exception("FTP connection failed to {$config['host']}:{$config['port']}");
            }

            // Login
            $loginSuccess = @ftp_login($connection, $config['username'] ?? 'anonymous', $config['password'] ?? '');

            if (!$loginSuccess) {
                throw new Exception("FTP login failed for user: {$config['username']}");
            }

            // Set passive mode
            if ($config['passive'] ?? true) {
                ftp_pasv($connection, true);
            }

            // Change to configured path if set
            if (!empty($config['path']) && $config['path'] !== '/') {
                @ftp_chdir($connection, $config['path']);
            }

            // Store connection
            $this->connections[$name] = $connection;

            // Update stats
            $this->stats[$name] = [
                'attempts' => ($this->stats[$name]['attempts'] ?? 0) + 1,
                'connected' => true,
                'last_connect' => time(),
                'connect_time' => microtime(true) - $startTime,
            ];

            if (app_debug()) {
                error_log("FTP connected successfully to {$config['host']} as '{$name}'");
            }

            return $connection;

        } catch (Exception $e) {
            // Update stats
            $this->stats[$name] = [
                'attempts' => ($this->stats[$name]['attempts'] ?? 0) + 1,
                'connected' => false,
                'last_error' => $e->getMessage(),
                'last_attempt' => time(),
            ];

            $errorMsg = "FTP connection '{$name}' failed: " . $e->getMessage();

            if (app_debug()) {
                error_log($errorMsg);
            }

            throw new Exception($errorMsg);
        }
    }

    /**
     * Check if connection is alive
     */
    private function isConnectionAlive(string $name): bool
    {
        if (!isset($this->connections[$name])) {
            return false;
        }

        // Try to execute a simple command
        return @ftp_pwd($this->connections[$name]) !== false;
    }

    /**
     * Get configuration for a specific connection
     */
    public function getConnectionConfig(?string $name = null): ?array
    {
        if ($name === null) {
            $name = $this->config['default'] ?? 'default';
        }

        return $this->config['connections'][$name] ?? null;
    }

    /**
     * Get list of available connections
     */
    public function getAvailableConnections(): array
    {
        return array_keys($this->config['connections'] ?? []);
    }

    /**
     * Get connection statistics
     */
    public function getConnectionStats(?string $name = null): array
    {
        if ($name !== null) {
            return $this->stats[$name] ?? [
                'attempts' => 0,
                'connected' => false,
            ];
        }

        return $this->stats;
    }

    /**
     * Test all connections
     */
    public function testAllConnections(): array
    {
        $results = [];

        foreach ($this->getAvailableConnections() as $name) {
            try {
                $connection = $this->getConnection($name);
                $results[$name] = [
                    'status' => 'healthy',
                    'connected' => true,
                    'pwd' => @ftp_pwd($connection),
                ];
            } catch (Exception $e) {
                $results[$name] = [
                    'status' => 'failed',
                    'connected' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Reset a specific connection
     */
    public function resetConnection(?string $name = null)
    {
        if ($name === null) {
            $name = $this->config['default'] ?? 'default';
        }

        if (isset($this->connections[$name])) {
            @ftp_close($this->connections[$name]);
            unset($this->connections[$name]);
        }
    }

    /**
     * Reset all connections
     */
    public function resetAllConnections()
    {
        foreach ($this->connections as $name => $connection) {
            @ftp_close($connection);
        }

        $this->connections = [];
    }

    /**
     * Upload a file
     *
     * @param string $localFile Local file path
     * @param string $remoteFile Remote file path
     * @param string|null $connection Connection name
     * @param int $mode Transfer mode (FTP_ASCII or FTP_BINARY)
     * @return bool Success
     */
    public function upload(string $localFile, string $remoteFile, ?string $connection = null, int $mode = FTP_BINARY): bool
    {
        $conn = $this->getConnection($connection);

        if (!file_exists($localFile)) {
            throw new Exception("Local file not found: {$localFile}");
        }

        return ftp_put($conn, $remoteFile, $localFile, $mode);
    }

    /**
     * Download a file
     *
     * @param string $remoteFile Remote file path
     * @param string $localFile Local file path
     * @param string|null $connection Connection name
     * @param int $mode Transfer mode (FTP_ASCII or FTP_BINARY)
     * @return bool Success
     */
    public function download(string $remoteFile, string $localFile, ?string $connection = null, int $mode = FTP_BINARY): bool
    {
        $conn = $this->getConnection($connection);

        $dir = dirname($localFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return ftp_get($conn, $localFile, $remoteFile, $mode);
    }

    /**
     * Delete a file
     */
    public function delete(string $remoteFile, ?string $connection = null): bool
    {
        $conn = $this->getConnection($connection);
        return ftp_delete($conn, $remoteFile);
    }

    /**
     * List files in directory
     */
    public function listFiles(string $directory = '.', ?string $connection = null): array
    {
        $conn = $this->getConnection($connection);
        $files = ftp_nlist($conn, $directory);
        return $files ?: [];
    }

    /**
     * Create directory
     */
    public function mkdir(string $directory, ?string $connection = null): bool
    {
        $conn = $this->getConnection($connection);
        return ftp_mkdir($conn, $directory) !== false;
    }

    /**
     * Check if file exists
     */
    public function exists(string $remoteFile, ?string $connection = null): bool
    {
        $conn = $this->getConnection($connection);
        $files = $this->listFiles(dirname($remoteFile), $connection);
        return in_array(basename($remoteFile), $files);
    }

    /**
     * Get file size
     */
    public function size(string $remoteFile, ?string $connection = null): int
    {
        $conn = $this->getConnection($connection);
        return ftp_size($conn, $remoteFile);
    }

    /**
     * Destructor - close all connections
     */
    public function __destruct()
    {
        $this->resetAllConnections();
    }
}
