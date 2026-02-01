<?php

namespace Vireo\Framework\Storage\Drivers;

use Vireo\Framework\Storage\StorageInterface;
use Vireo\Framework\Storage\Connections\FTPConnection;
use Exception;

/**
 * FTP Storage Driver
 *
 * Store files on FTP server using the FTP connection system
 */
class FTPStorage implements StorageInterface
{
    protected string $connection;
    protected string $root;
    protected ?string $baseUrl;

    public function __construct(array $config)
    {
        $this->connection = $config['connection'] ?? 'default';
        $this->root = rtrim($config['root'] ?? '/', '/');
        $this->baseUrl = $config['url'] ?? null;
    }

    /**
     * Get full path
     */
    protected function getFullPath(string $path): string
    {
        if ($this->root === '' || $this->root === '/') {
            return '/' . ltrim($path, '/');
        }
        return $this->root . '/' . ltrim($path, '/');
    }

    /**
     * Write content to a file
     */
    public function put(string $path, string $contents): bool
    {
        // Create temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'ftp_');
        file_put_contents($tempFile, $contents);

        try {
            $result = FTPConnection::getInstance()->upload($tempFile, $this->getFullPath($path), $this->connection);
            unlink($tempFile);
            return $result;
        } catch (Exception $e) {
            logger('storage')->error('FTP storage put failed', [
                'driver' => 'ftp',
                'operation' => 'put',
                'path' => $path,
                'connection' => $this->connection,
                'error' => $e->getMessage()
            ]);
            unlink($tempFile);
            return false;
        }
    }

    /**
     * Get file contents
     */
    public function get(string $path): ?string
    {
        // Download to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'ftp_');

        try {
            $result = FTPConnection::getInstance()->download($this->getFullPath($path), $tempFile, $this->connection);

            if ($result) {
                $contents = file_get_contents($tempFile);
                unlink($tempFile);
                return $contents ?: null;
            }

            unlink($tempFile);
            return null;
        } catch (Exception $e) {
            logger('storage')->error('FTP storage get failed', [
                'driver' => 'ftp',
                'operation' => 'get',
                'path' => $path,
                'connection' => $this->connection,
                'error' => $e->getMessage()
            ]);
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            return null;
        }
    }

    /**
     * Check if file exists
     */
    public function exists(string $path): bool
    {
        try {
            return FTPConnection::getInstance()->exists($this->getFullPath($path), $this->connection);
        } catch (Exception $e) {
            logger('storage')->error('FTP storage exists check failed', [
                'driver' => 'ftp',
                'operation' => 'exists',
                'path' => $path,
                'connection' => $this->connection,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Delete a file
     */
    public function delete(string $path): bool
    {
        try {
            return FTPConnection::getInstance()->delete($this->getFullPath($path), $this->connection);
        } catch (Exception $e) {
            logger('storage')->error('FTP storage delete failed', [
                'driver' => 'ftp',
                'operation' => 'delete',
                'path' => $path,
                'connection' => $this->connection,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Copy a file
     */
    public function copy(string $from, string $to): bool
    {
        // FTP doesn't support direct copy, so download and re-upload
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
        // Download, upload to new location, delete old
        if (!$this->copy($from, $to)) {
            return false;
        }

        return $this->delete($from);
    }

    /**
     * Get file size in bytes
     */
    public function size(string $path): int
    {
        try {
            return FTPConnection::getInstance()->size($this->getFullPath($path), $this->connection);
        } catch (Exception $e) {
            logger('storage')->error('FTP storage size check failed', [
                'driver' => 'ftp',
                'operation' => 'size',
                'path' => $path,
                'connection' => $this->connection,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get file last modified time
     */
    public function lastModified(string $path): int
    {
        // FTP doesn't easily support last modified time
        // Would need to parse ftp_rawlist
        return 0;
    }

    /**
     * List files in directory
     */
    public function files(string $directory = ''): array
    {
        try {
            $allItems = FTPConnection::getInstance()->listFiles($this->getFullPath($directory), $this->connection);

            // Filter out directories (basic check - items ending with /)
            $files = [];
            foreach ($allItems as $item) {
                if (!str_ends_with($item, '/') && $item !== '.' && $item !== '..') {
                    $files[] = ltrim($directory . '/' . basename($item), '/');
                }
            }

            return $files;
        } catch (Exception $e) {
            logger('storage')->error('FTP storage files list failed', [
                'driver' => 'ftp',
                'operation' => 'files',
                'directory' => $directory,
                'connection' => $this->connection,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * List all files recursively
     */
    public function allFiles(string $directory = ''): array
    {
        // Not easily supported by FTP without recursive traversal
        // Return files in current directory only
        return $this->files($directory);
    }

    /**
     * List directories
     */
    public function directories(string $directory = ''): array
    {
        try {
            $allItems = FTPConnection::getInstance()->listFiles($this->getFullPath($directory), $this->connection);

            // Filter directories (basic check - items ending with /)
            $directories = [];
            foreach ($allItems as $item) {
                if (str_ends_with($item, '/') && $item !== '.' && $item !== '..') {
                    $directories[] = ltrim($directory . '/' . rtrim(basename($item), '/'), '/');
                }
            }

            return $directories;
        } catch (Exception $e) {
            logger('storage')->error('FTP storage directories list failed', [
                'driver' => 'ftp',
                'operation' => 'directories',
                'directory' => $directory,
                'connection' => $this->connection,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Create a directory
     */
    public function makeDirectory(string $path): bool
    {
        try {
            return FTPConnection::getInstance()->mkdir($this->getFullPath($path), $this->connection);
        } catch (Exception $e) {
            logger('storage')->error('FTP storage make directory failed', [
                'driver' => 'ftp',
                'operation' => 'makeDirectory',
                'path' => $path,
                'connection' => $this->connection,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Delete a directory
     */
    public function deleteDirectory(string $path): bool
    {
        // FTP doesn't support recursive directory deletion easily
        // Would need to recursively delete all files first
        return false;
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

        try {
            $result = FTPConnection::getInstance()->upload($uploadedFile['tmp_name'], $this->getFullPath($path), $this->connection);
            return $result ? $path : null;
        } catch (Exception $e) {
            logger('storage')->error('FTP storage put file failed', [
                'driver' => 'ftp',
                'operation' => 'putFile',
                'directory' => $directory,
                'filename' => $filename ?? 'unknown',
                'connection' => $this->connection,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
