<?php

namespace Vireo\Framework\Storage\Drivers;

use Vireo\Framework\Storage\StorageInterface;
use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Local Filesystem Storage Driver
 *
 * Store files on the local filesystem
 */
class LocalStorage implements StorageInterface
{
    protected string $root;
    protected ?string $baseUrl;

    public function __construct(array $config)
    {
        $this->root = rtrim($config['root'] ?? ROOT_PATH . '/storage', '/');
        $this->baseUrl = $config['url'] ?? null;

        // Ensure root directory exists
        if (!is_dir($this->root)) {
            mkdir($this->root, 0755, true);
        }
    }

    /**
     * Get full path
     */
    protected function getFullPath(string $path): string
    {
        return $this->root . '/' . ltrim($path, '/');
    }

    /**
     * Write content to a file
     */
    public function put(string $path, string $contents): bool
    {
        $fullPath = $this->getFullPath($path);

        // Create directory if it doesn't exist
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents($fullPath, $contents) !== false;
    }

    /**
     * Get file contents
     */
    public function get(string $path): ?string
    {
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath)) {
            return null;
        }

        return file_get_contents($fullPath) ?: null;
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
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath)) {
            return false;
        }

        return unlink($fullPath);
    }

    /**
     * Copy a file
     */
    public function copy(string $from, string $to): bool
    {
        $fromPath = $this->getFullPath($from);
        $toPath = $this->getFullPath($to);

        if (!file_exists($fromPath)) {
            return false;
        }

        // Create directory if it doesn't exist
        $dir = dirname($toPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return copy($fromPath, $toPath);
    }

    /**
     * Move a file
     */
    public function move(string $from, string $to): bool
    {
        $fromPath = $this->getFullPath($from);
        $toPath = $this->getFullPath($to);

        if (!file_exists($fromPath)) {
            return false;
        }

        // Create directory if it doesn't exist
        $dir = dirname($toPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return rename($fromPath, $toPath);
    }

    /**
     * Get file size in bytes
     */
    public function size(string $path): int
    {
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath)) {
            return 0;
        }

        return filesize($fullPath) ?: 0;
    }

    /**
     * Get file last modified time
     */
    public function lastModified(string $path): int
    {
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath)) {
            return 0;
        }

        return filemtime($fullPath) ?: 0;
    }

    /**
     * List files in directory
     */
    public function files(string $directory = ''): array
    {
        $fullPath = $this->getFullPath($directory);

        if (!is_dir($fullPath)) {
            return [];
        }

        $files = [];
        foreach (scandir($fullPath) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $fullPath . '/' . $file;
            if (is_file($filePath)) {
                $files[] = ltrim($directory . '/' . $file, '/');
            }
        }

        return $files;
    }

    /**
     * List all files recursively
     */
    public function allFiles(string $directory = ''): array
    {
        $fullPath = $this->getFullPath($directory);

        if (!is_dir($fullPath)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($this->root . '/', '', $file->getPathname());
                $files[] = $relativePath;
            }
        }

        return $files;
    }

    /**
     * List directories
     */
    public function directories(string $directory = ''): array
    {
        $fullPath = $this->getFullPath($directory);

        if (!is_dir($fullPath)) {
            return [];
        }

        $directories = [];
        foreach (scandir($fullPath) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $fullPath . '/' . $item;
            if (is_dir($itemPath)) {
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
        $fullPath = $this->getFullPath($path);

        if (is_dir($fullPath)) {
            return true;
        }

        return mkdir($fullPath, 0755, true);
    }

    /**
     * Delete a directory
     */
    public function deleteDirectory(string $path): bool
    {
        $fullPath = $this->getFullPath($path);

        if (!is_dir($fullPath)) {
            return false;
        }

        // Delete all files and subdirectories
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        return rmdir($fullPath);
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
     * Upload a file from $_FILES
     */
    public function putFile(string $directory, array $uploadedFile): ?string
    {
        if (!isset($uploadedFile['tmp_name']) || !isset($uploadedFile['name'])) {
            return null;
        }

        $filename = basename($uploadedFile['name']);
        $path = ltrim($directory . '/' . $filename, '/');
        $fullPath = $this->getFullPath($path);

        // Create directory if it doesn't exist
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (move_uploaded_file($uploadedFile['tmp_name'], $fullPath)) {
            return $path;
        }

        return null;
    }
}
