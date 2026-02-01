<?php

namespace Framework\Storage;

/**
 * Storage Facade
 *
 * Provides static access to storage functionality
 *
 * Usage:
 * Storage::put('file.txt', 'content')          // Use default disk
 * Storage::disk('ftp')->put('file.txt', '...')  // Use specific disk
 * Storage::disk('s3')->url('file.txt')         // Get URL
 */
class Storage
{
    /**
     * Get a storage disk
     */
    public static function disk(?string $name = null): StorageInterface
    {
        return StorageManager::getInstance()->disk($name);
    }

    /**
     * Get available disks
     */
    public static function availableDisks(): array
    {
        return StorageManager::getInstance()->availableDisks();
    }

    /**
     * Get default disk name
     */
    public static function getDefaultDisk(): string
    {
        return StorageManager::getInstance()->getDefaultDisk();
    }

    /**
     * Write content to a file
     */
    public static function put(string $path, string $contents): bool
    {
        return StorageManager::getInstance()->put($path, $contents);
    }

    /**
     * Get file contents
     */
    public static function get(string $path): ?string
    {
        return StorageManager::getInstance()->get($path);
    }

    /**
     * Check if file exists
     */
    public static function exists(string $path): bool
    {
        return StorageManager::getInstance()->exists($path);
    }

    /**
     * Delete a file
     */
    public static function delete(string $path): bool
    {
        return StorageManager::getInstance()->delete($path);
    }

    /**
     * Copy a file
     */
    public static function copy(string $from, string $to): bool
    {
        return StorageManager::getInstance()->copy($from, $to);
    }

    /**
     * Move a file
     */
    public static function move(string $from, string $to): bool
    {
        return StorageManager::getInstance()->move($from, $to);
    }

    /**
     * Get file size
     */
    public static function size(string $path): int
    {
        return StorageManager::getInstance()->size($path);
    }

    /**
     * Get file last modified time
     */
    public static function lastModified(string $path): int
    {
        return StorageManager::getInstance()->lastModified($path);
    }

    /**
     * List files in directory
     */
    public static function files(string $directory = ''): array
    {
        return StorageManager::getInstance()->files($directory);
    }

    /**
     * List all files recursively
     */
    public static function allFiles(string $directory = ''): array
    {
        return StorageManager::getInstance()->allFiles($directory);
    }

    /**
     * List directories
     */
    public static function directories(string $directory = ''): array
    {
        return StorageManager::getInstance()->directories($directory);
    }

    /**
     * Create a directory
     */
    public static function makeDirectory(string $path): bool
    {
        return StorageManager::getInstance()->makeDirectory($path);
    }

    /**
     * Delete a directory
     */
    public static function deleteDirectory(string $path): bool
    {
        return StorageManager::getInstance()->deleteDirectory($path);
    }

    /**
     * Get file URL
     */
    public static function url(string $path): ?string
    {
        return StorageManager::getInstance()->url($path);
    }

    /**
     * Put file from uploaded file ($_FILES)
     */
    public static function putFile(string $directory, array $uploadedFile): ?string
    {
        return StorageManager::getInstance()->putFile($directory, $uploadedFile);
    }
}
