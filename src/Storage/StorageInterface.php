<?php

namespace Framework\Storage;

/**
 * Storage Interface
 *
 * Contract for all storage drivers (Local, FTP, SFTP, S3, etc.)
 */
interface StorageInterface
{
    /**
     * Write content to a file
     */
    public function put(string $path, string $contents): bool;

    /**
     * Get file contents
     */
    public function get(string $path): ?string;

    /**
     * Check if file exists
     */
    public function exists(string $path): bool;

    /**
     * Delete a file
     */
    public function delete(string $path): bool;

    /**
     * Copy a file
     */
    public function copy(string $from, string $to): bool;

    /**
     * Move a file
     */
    public function move(string $from, string $to): bool;

    /**
     * Get file size in bytes
     */
    public function size(string $path): int;

    /**
     * Get file last modified time
     */
    public function lastModified(string $path): int;

    /**
     * List files in directory
     */
    public function files(string $directory = ''): array;

    /**
     * List all files recursively
     */
    public function allFiles(string $directory = ''): array;

    /**
     * List directories
     */
    public function directories(string $directory = ''): array;

    /**
     * Create a directory
     */
    public function makeDirectory(string $path): bool;

    /**
     * Delete a directory
     */
    public function deleteDirectory(string $path): bool;

    /**
     * Get file URL (if supported)
     */
    public function url(string $path): ?string;

    /**
     * Store an uploaded file
     *
     * @param string $directory Target directory
     * @param array $uploadedFile Uploaded file from $_FILES
     * @return string|null Stored file path or null on failure
     */
    public function putFile(string $directory, array $uploadedFile): ?string;
}
