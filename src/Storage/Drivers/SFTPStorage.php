<?php

namespace Framework\Storage\Drivers;

use Framework\Storage\StorageInterface;
use Exception;

/**
 * SFTP Storage Driver
 *
 * Store files on SFTP server using SSH2 extension
 * Requires: php-ssh2 extension
 */
class SFTPStorage implements StorageInterface
{
    protected $connection;
    protected $sftp;
    protected string $root;
    protected ?string $baseUrl;
    protected array $config;

    public function __construct(array $config)
    {
        if (!extension_loaded('ssh2')) {
            throw new Exception('SFTP storage requires ssh2 PHP extension');
        }

        $this->config = $config;
        $this->root = rtrim($config['root'] ?? '/', '/');
        $this->baseUrl = $config['url'] ?? null;
        $this->connect();
    }

    /**
     * Connect to SFTP server
     */
    protected function connect(): void
    {
        $this->connection = ssh2_connect(
            $this->config['host'],
            $this->config['port'] ?? 22
        );

        if (!$this->connection) {
            throw new Exception("SFTP connection failed to {$this->config['host']}");
        }

        // Authenticate
        if (isset($this->config['privateKey'])) {
            // Key-based authentication
            $authenticated = ssh2_auth_pubkey_file(
                $this->connection,
                $this->config['username'],
                $this->config['publicKey'] ?? null,
                $this->config['privateKey'],
                $this->config['passphrase'] ?? ''
            );
        } else {
            // Password authentication
            $authenticated = ssh2_auth_password(
                $this->connection,
                $this->config['username'],
                $this->config['password'] ?? ''
            );
        }

        if (!$authenticated) {
            throw new Exception('SFTP authentication failed');
        }

        $this->sftp = ssh2_sftp($this->connection);

        if (!$this->sftp) {
            throw new Exception('SFTP initialization failed');
        }
    }

    /**
     * Get full path with SFTP wrapper
     */
    protected function getFullPath(string $path): string
    {
        $fullPath = $this->root . '/' . ltrim($path, '/');
        return 'ssh2.sftp://' . intval($this->sftp) . $fullPath;
    }

    /**
     * Get real path (without wrapper)
     */
    protected function getRealPath(string $path): string
    {
        return $this->root . '/' . ltrim($path, '/');
    }

    /**
     * Write content to a file
     */
    public function put(string $path, string $contents): bool
    {
        $result = @file_put_contents($this->getFullPath($path), $contents);
        if ($result === false) {
            logger('storage')->error('SFTP storage put failed', [
                'driver' => 'sftp',
                'operation' => 'put',
                'path' => $path,
                'host' => $this->config['host'] ?? 'unknown',
                'error' => error_get_last()['message'] ?? 'Unknown error'
            ]);
            return false;
        }
        return true;
    }

    /**
     * Get file contents
     */
    public function get(string $path): ?string
    {
        $contents = @file_get_contents($this->getFullPath($path));
        if ($contents === false) {
            logger('storage')->error('SFTP storage get failed', [
                'driver' => 'sftp',
                'operation' => 'get',
                'path' => $path,
                'host' => $this->config['host'] ?? 'unknown',
                'error' => error_get_last()['message'] ?? 'File not found or unreadable'
            ]);
            return null;
        }
        return $contents;
    }

    /**
     * Check if file exists
     */
    public function exists(string $path): bool
    {
        return file_exists($this->getFullPath($path));
    }

    /**
     * Delete a file
     */
    public function delete(string $path): bool
    {
        $result = @unlink($this->getFullPath($path));
        if (!$result) {
            logger('storage')->error('SFTP storage delete failed', [
                'driver' => 'sftp',
                'operation' => 'delete',
                'path' => $path,
                'host' => $this->config['host'] ?? 'unknown',
                'error' => error_get_last()['message'] ?? 'Failed to delete file'
            ]);
        }
        return $result;
    }

    /**
     * Copy a file
     */
    public function copy(string $from, string $to): bool
    {
        $contents = $this->get($from);

        if ($contents === null) {
            return false;
        }

        return $this->put($to, $contents);
    }

    /**
     * Move a file
     */
    public function move(string $from, string $to): bool
    {
        $result = @ssh2_sftp_rename($this->sftp, $this->getRealPath($from), $this->getRealPath($to));
        if (!$result) {
            logger('storage')->error('SFTP storage move failed', [
                'driver' => 'sftp',
                'operation' => 'move',
                'from' => $from,
                'to' => $to,
                'host' => $this->config['host'] ?? 'unknown',
                'error' => error_get_last()['message'] ?? 'Failed to move file'
            ]);
        }
        return $result;
    }

    /**
     * Get file size in bytes
     */
    public function size(string $path): int
    {
        $size = @filesize($this->getFullPath($path));
        return $size !== false ? $size : 0;
    }

    /**
     * Get file last modified time
     */
    public function lastModified(string $path): int
    {
        $time = @filemtime($this->getFullPath($path));
        return $time !== false ? $time : 0;
    }

    /**
     * List files in directory
     */
    public function files(string $directory = ''): array
    {
        $fullPath = $this->getFullPath($directory);
        $items = @scandir($fullPath);

        if (!$items) {
            logger('storage')->error('SFTP storage files list failed', [
                'driver' => 'sftp',
                'operation' => 'files',
                'directory' => $directory,
                'host' => $this->config['host'] ?? 'unknown',
                'error' => error_get_last()['message'] ?? 'Failed to list files'
            ]);
            return [];
        }

        $files = [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (is_file($fullPath . '/' . $item)) {
                $files[] = ltrim($directory . '/' . $item, '/');
            }
        }

        return $files;
    }

    /**
     * List all files recursively
     */
    public function allFiles(string $directory = ''): array
    {
        // Basic implementation - just return files in current directory
        return $this->files($directory);
    }

    /**
     * List directories
     */
    public function directories(string $directory = ''): array
    {
        $fullPath = $this->getFullPath($directory);
        $items = @scandir($fullPath);

        if (!$items) {
            logger('storage')->error('SFTP storage directories list failed', [
                'driver' => 'sftp',
                'operation' => 'directories',
                'directory' => $directory,
                'host' => $this->config['host'] ?? 'unknown',
                'error' => error_get_last()['message'] ?? 'Failed to list directories'
            ]);
            return [];
        }

        $directories = [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (is_dir($fullPath . '/' . $item)) {
                $directories[] = ltrim($directory . '/' . $item, '/');
            }
        }

        return $directories;
    }

    /**
     * Create a directory
     */
    public function makeDirectory(string $path): bool
    {
        $result = @ssh2_sftp_mkdir($this->sftp, $this->getRealPath($path), 0755, true);
        if (!$result) {
            logger('storage')->error('SFTP storage make directory failed', [
                'driver' => 'sftp',
                'operation' => 'makeDirectory',
                'path' => $path,
                'host' => $this->config['host'] ?? 'unknown',
                'error' => error_get_last()['message'] ?? 'Failed to create directory'
            ]);
        }
        return $result;
    }

    /**
     * Delete a directory
     */
    public function deleteDirectory(string $path): bool
    {
        $result = @ssh2_sftp_rmdir($this->sftp, $this->getRealPath($path));
        if (!$result) {
            logger('storage')->error('SFTP storage delete directory failed', [
                'driver' => 'sftp',
                'operation' => 'deleteDirectory',
                'path' => $path,
                'host' => $this->config['host'] ?? 'unknown',
                'error' => error_get_last()['message'] ?? 'Failed to delete directory'
            ]);
        }
        return $result;
    }

    /**
     * Get file URL
     */
    public function url(string $path): ?string
    {
        if ($this->baseUrl === null) {
            return null;
        }

        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Store an uploaded file
     */
    public function putFile(string $directory, array $uploadedFile): ?string
    {
        if (!isset($uploadedFile['tmp_name']) || !isset($uploadedFile['name'])) {
            return null;
        }

        if (!is_uploaded_file($uploadedFile['tmp_name'])) {
            return null;
        }

        $filename = basename($uploadedFile['name']);
        $path = ltrim($directory . '/' . $filename, '/');

        $contents = file_get_contents($uploadedFile['tmp_name']);
        if ($contents === false) {
            return null;
        }

        return $this->put($path, $contents) ? $path : null;
    }
}
