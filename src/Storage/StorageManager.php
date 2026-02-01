<?php

namespace Vireo\Framework\Storage;

use Vireo\Framework\Storage\Drivers\LocalStorage;
use Vireo\Framework\Storage\Drivers\FTPStorage;
use Vireo\Framework\Storage\Drivers\SFTPStorage;
use Vireo\Framework\Storage\Drivers\S3Storage;
use Exception;

/**
 * Storage Manager
 *
 * Manages multiple storage disks and provides unified interface
 */
class StorageManager
{
    private static $instance = null;
    private array $config;
    private array $disks = [];

    private function __construct()
    {
        $this->loadConfig();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load storage configuration
     */
    private function loadConfig(): void
    {
        $configPath = ROOT_PATH . '/Config/Storage.php';

        if (!file_exists($configPath)) {
            throw new Exception("Storage configuration file not found: {$configPath}");
        }

        $this->config = require $configPath;
    }

    /**
     * Get a storage disk
     */
    public function disk(?string $name = null): StorageInterface
    {
        // Use default disk if no name provided
        if ($name === null) {
            $name = $this->config['default'] ?? 'local';
        }

        // Return cached disk if available
        if (isset($this->disks[$name])) {
            return $this->disks[$name];
        }

        // Create new disk
        $disk = $this->createDisk($name);
        $this->disks[$name] = $disk;

        return $disk;
    }

    /**
     * Create a storage disk
     */
    private function createDisk(string $name): StorageInterface
    {
        if (!isset($this->config['disks'][$name])) {
            throw new Exception("Storage disk '{$name}' not found in configuration");
        }

        $config = $this->config['disks'][$name];
        $driver = $config['driver'] ?? 'local';

        return match ($driver) {
            'local' => new LocalStorage($config),
            'ftp' => new FTPStorage($config),
            'sftp' => new SFTPStorage($config),
            's3' => new S3Storage($config),
            default => throw new Exception("Unsupported storage driver: {$driver}"),
        };
    }

    /**
     * Get list of available disks
     */
    public function availableDisks(): array
    {
        return array_keys($this->config['disks'] ?? []);
    }

    /**
     * Get default disk name
     */
    public function getDefaultDisk(): string
    {
        return $this->config['default'] ?? 'local';
    }

    /**
     * Write content to a file
     */
    public function put(string $path, string $contents, ?string $disk = null): bool
    {
        return $this->disk($disk)->put($path, $contents);
    }

    /**
     * Get file contents
     */
    public function get(string $path, ?string $disk = null): ?string
    {
        return $this->disk($disk)->get($path);
    }

    /**
     * Check if file exists
     */
    public function exists(string $path, ?string $disk = null): bool
    {
        return $this->disk($disk)->exists($path);
    }

    /**
     * Delete a file
     */
    public function delete(string $path, ?string $disk = null): bool
    {
        return $this->disk($disk)->delete($path);
    }

    /**
     * Copy a file
     */
    public function copy(string $from, string $to, ?string $disk = null): bool
    {
        return $this->disk($disk)->copy($from, $to);
    }

    /**
     * Move a file
     */
    public function move(string $from, string $to, ?string $disk = null): bool
    {
        return $this->disk($disk)->move($from, $to);
    }

    /**
     * Get file size
     */
    public function size(string $path, ?string $disk = null): int
    {
        return $this->disk($disk)->size($path);
    }

    /**
     * Get file last modified time
     */
    public function lastModified(string $path, ?string $disk = null): int
    {
        return $this->disk($disk)->lastModified($path);
    }

    /**
     * List files in directory
     */
    public function files(string $directory = '', ?string $disk = null): array
    {
        return $this->disk($disk)->files($directory);
    }

    /**
     * List all files recursively
     */
    public function allFiles(string $directory = '', ?string $disk = null): array
    {
        return $this->disk($disk)->allFiles($directory);
    }

    /**
     * List directories
     */
    public function directories(string $directory = '', ?string $disk = null): array
    {
        return $this->disk($disk)->directories($directory);
    }

    /**
     * Create a directory
     */
    public function makeDirectory(string $path, ?string $disk = null): bool
    {
        return $this->disk($disk)->makeDirectory($path);
    }

    /**
     * Delete a directory
     */
    public function deleteDirectory(string $path, ?string $disk = null): bool
    {
        return $this->disk($disk)->deleteDirectory($path);
    }

    /**
     * Get file URL
     */
    public function url(string $path, ?string $disk = null): ?string
    {
        return $this->disk($disk)->url($path);
    }

    /**
     * Put file from uploaded file ($_FILES)
     */
    public function putFile(string $directory, array $uploadedFile, ?string $disk = null): ?string
    {
        return $this->disk($disk)->putFile($directory, $uploadedFile);
    }
}
