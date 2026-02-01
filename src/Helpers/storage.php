<?php

use Framework\Storage\Storage;
use Framework\Storage\StorageInterface;

/**
 * Storage Helper Functions
 * File: Framework/Helpers/storage.php
 *
 * Convenient helper functions for file storage operations
 */

// ============== MAIN STORAGE HELPERS ==============

/**
 * Get a storage disk
 *
 * Usage:
 * storage()                    // Get default disk
 * storage('ftp')               // Get FTP disk
 * storage('s3')                // Get S3 disk
 *
 * @param string|null $disk Disk name (null = default)
 * @return StorageInterface Storage disk instance
 */
if (!function_exists('storage')) {
    function storage(?string $disk = null): StorageInterface {
        return Storage::disk($disk);
    }
}

/**
 * Write content to a file
 *
 * Usage:
 * storage_put('file.txt', 'content')               // Default disk
 * storage_put('file.txt', 'content', 'ftp')        // FTP disk
 *
 * @param string $path File path
 * @param string $contents File contents
 * @param string|null $disk Disk name
 * @return bool Success
 */
if (!function_exists('storage_put')) {
    function storage_put(string $path, string $contents, ?string $disk = null): bool {
        return Storage::disk($disk)->put($path, $contents);
    }
}

/**
 * Get file contents
 *
 * Usage:
 * $content = storage_get('file.txt');              // Default disk
 * $content = storage_get('file.txt', 'ftp');       // FTP disk
 *
 * @param string $path File path
 * @param string|null $disk Disk name
 * @return string|null File contents or null if not found
 */
if (!function_exists('storage_get')) {
    function storage_get(string $path, ?string $disk = null): ?string {
        return Storage::disk($disk)->get($path);
    }
}

/**
 * Check if file exists
 *
 * Usage:
 * if (storage_exists('file.txt')) { ... }
 *
 * @param string $path File path
 * @param string|null $disk Disk name
 * @return bool True if file exists
 */
if (!function_exists('storage_exists')) {
    function storage_exists(string $path, ?string $disk = null): bool {
        return Storage::disk($disk)->exists($path);
    }
}

/**
 * Delete a file
 *
 * Usage:
 * storage_delete('file.txt');                      // Default disk
 * storage_delete('file.txt', 'ftp');               // FTP disk
 *
 * @param string $path File path
 * @param string|null $disk Disk name
 * @return bool Success
 */
if (!function_exists('storage_delete')) {
    function storage_delete(string $path, ?string $disk = null): bool {
        return Storage::disk($disk)->delete($path);
    }
}

/**
 * Copy a file
 *
 * Usage:
 * storage_copy('old.txt', 'new.txt');              // Default disk
 * storage_copy('old.txt', 'new.txt', 'ftp');       // FTP disk
 *
 * @param string $from Source path
 * @param string $to Destination path
 * @param string|null $disk Disk name
 * @return bool Success
 */
if (!function_exists('storage_copy')) {
    function storage_copy(string $from, string $to, ?string $disk = null): bool {
        return Storage::disk($disk)->copy($from, $to);
    }
}

/**
 * Move a file
 *
 * Usage:
 * storage_move('old.txt', 'new.txt');              // Default disk
 * storage_move('old.txt', 'new.txt', 'ftp');       // FTP disk
 *
 * @param string $from Source path
 * @param string $to Destination path
 * @param string|null $disk Disk name
 * @return bool Success
 */
if (!function_exists('storage_move')) {
    function storage_move(string $from, string $to, ?string $disk = null): bool {
        return Storage::disk($disk)->move($from, $to);
    }
}

/**
 * Get file size in bytes
 *
 * Usage:
 * $size = storage_size('file.txt');
 *
 * @param string $path File path
 * @param string|null $disk Disk name
 * @return int File size in bytes
 */
if (!function_exists('storage_size')) {
    function storage_size(string $path, ?string $disk = null): int {
        return Storage::disk($disk)->size($path);
    }
}

/**
 * Get file last modified time (Unix timestamp)
 *
 * Usage:
 * $timestamp = storage_last_modified('file.txt');
 *
 * @param string $path File path
 * @param string|null $disk Disk name
 * @return int Unix timestamp
 */
if (!function_exists('storage_last_modified')) {
    function storage_last_modified(string $path, ?string $disk = null): int {
        return Storage::disk($disk)->lastModified($path);
    }
}

/**
 * List files in directory
 *
 * Usage:
 * $files = storage_files('documents');             // List files in documents/
 * $files = storage_files('', 'ftp');               // List files in FTP root
 *
 * @param string $directory Directory path
 * @param string|null $disk Disk name
 * @return array Array of file paths
 */
if (!function_exists('storage_files')) {
    function storage_files(string $directory = '', ?string $disk = null): array {
        return Storage::disk($disk)->files($directory);
    }
}

/**
 * List all files recursively
 *
 * Usage:
 * $files = storage_all_files('documents');         // All files in documents/
 *
 * @param string $directory Directory path
 * @param string|null $disk Disk name
 * @return array Array of file paths
 */
if (!function_exists('storage_all_files')) {
    function storage_all_files(string $directory = '', ?string $disk = null): array {
        return Storage::disk($disk)->allFiles($directory);
    }
}

/**
 * List directories
 *
 * Usage:
 * $dirs = storage_directories('documents');
 *
 * @param string $directory Directory path
 * @param string|null $disk Disk name
 * @return array Array of directory paths
 */
if (!function_exists('storage_directories')) {
    function storage_directories(string $directory = '', ?string $disk = null): array {
        return Storage::disk($disk)->directories($directory);
    }
}

/**
 * Create a directory
 *
 * Usage:
 * storage_mkdir('documents/2024');
 *
 * @param string $path Directory path
 * @param string|null $disk Disk name
 * @return bool Success
 */
if (!function_exists('storage_mkdir')) {
    function storage_mkdir(string $path, ?string $disk = null): bool {
        return Storage::disk($disk)->makeDirectory($path);
    }
}

/**
 * Delete a directory
 *
 * Usage:
 * storage_rmdir('documents/temp');
 *
 * @param string $path Directory path
 * @param string|null $disk Disk name
 * @return bool Success
 */
if (!function_exists('storage_rmdir')) {
    function storage_rmdir(string $path, ?string $disk = null): bool {
        return Storage::disk($disk)->deleteDirectory($path);
    }
}

/**
 * Get file URL
 *
 * Usage:
 * $url = storage_url('image.jpg');
 * echo "<img src=\"{$url}\" />";
 *
 * @param string $path File path
 * @param string|null $disk Disk name
 * @return string|null Public URL or null if not available
 */
if (!function_exists('storage_url')) {
    function storage_url(string $path, ?string $disk = null): ?string {
        return Storage::disk($disk)->url($path);
    }
}

/**
 * Upload a file from $_FILES
 *
 * Usage:
 * $path = storage_upload('avatars', $_FILES['avatar']);
 *
 * @param string $directory Target directory
 * @param array $uploadedFile Uploaded file from $_FILES
 * @param string|null $disk Disk name
 * @return string|null Stored file path or null on failure
 */
if (!function_exists('storage_upload')) {
    function storage_upload(string $directory, array $uploadedFile, ?string $disk = null): ?string {
        return Storage::disk($disk)->putFile($directory, $uploadedFile);
    }
}

/**
 * Get list of available storage disks
 *
 * Usage:
 * $disks = storage_disks();
 * // Returns: ['local', 'public', 'ftp', 's3', ...]
 *
 * @return array Array of disk names
 */
if (!function_exists('storage_disks')) {
    function storage_disks(): array {
        return Storage::availableDisks();
    }
}

/**
 * Get default storage disk name
 *
 * Usage:
 * $default = storage_default_disk();
 * // Returns: 'local'
 *
 * @return string Default disk name
 */
if (!function_exists('storage_default_disk')) {
    function storage_default_disk(): string {
        return Storage::getDefaultDisk();
    }
}

/**
 * Quick file write helper
 *
 * Usage:
 * file_store('file.txt', 'content');
 * file_store('file.txt', 'content', 'ftp');
 *
 * Alias for storage_put()
 */
if (!function_exists('file_store')) {
    function file_store(string $path, string $contents, ?string $disk = null): bool {
        return storage_put($path, $contents, $disk);
    }
}

/**
 * Quick file read helper
 *
 * Usage:
 * $content = file_retrieve('file.txt');
 * $content = file_retrieve('file.txt', 'ftp');
 *
 * Alias for storage_get()
 */
if (!function_exists('file_retrieve')) {
    function file_retrieve(string $path, ?string $disk = null): ?string {
        return storage_get($path, $disk);
    }
}

// ============== FTP CONNECTION HELPERS ==============

use Framework\Storage\Connections\FTPConnection;

/**
 * Get FTP connection
 *
 * Usage:
 * $ftp = ftp_connection();                      // Default connection
 * $ftp = ftp_connection('backup');              // Specific connection
 *
 * @param string|null $name Connection name
 * @return \FTP\Connection FTP connection object (PHP 8.1+)
 */
if (!function_exists('ftp_connection')) {
    function ftp_connection(?string $name = null): \FTP\Connection {
        return FTPConnection::getInstance()->getConnection($name);
    }
}

/**
 * Upload file via FTP
 *
 * Usage:
 * ftp_upload('local.txt', 'remote.txt');                  // Default connection
 * ftp_upload('local.txt', 'remote.txt', 'backup');        // Specific connection
 *
 * @param string $localFile Local file path
 * @param string $remoteFile Remote file path
 * @param string|null $connection Connection name
 * @param int $mode Transfer mode (FTP_ASCII or FTP_BINARY)
 * @return bool Success
 */
if (!function_exists('ftp_upload')) {
    function ftp_upload(string $localFile, string $remoteFile, ?string $connection = null, int $mode = FTP_BINARY): bool {
        return FTPConnection::getInstance()->upload($localFile, $remoteFile, $connection, $mode);
    }
}

/**
 * Download file via FTP
 *
 * Usage:
 * ftp_download('remote.txt', 'local.txt');                // Default connection
 * ftp_download('remote.txt', 'local.txt', 'backup');      // Specific connection
 *
 * @param string $remoteFile Remote file path
 * @param string $localFile Local file path
 * @param string|null $connection Connection name
 * @param int $mode Transfer mode (FTP_ASCII or FTP_BINARY)
 * @return bool Success
 */
if (!function_exists('ftp_download')) {
    function ftp_download(string $remoteFile, string $localFile, ?string $connection = null, int $mode = FTP_BINARY): bool {
        return FTPConnection::getInstance()->download($remoteFile, $localFile, $connection, $mode);
    }
}

/**
 * Delete file via FTP
 *
 * Usage:
 * ftp_delete_file('remote.txt');                          // Default connection
 * ftp_delete_file('remote.txt', 'backup');                // Specific connection
 *
 * @param string $remoteFile Remote file path
 * @param string|null $connection Connection name
 * @return bool Success
 */
if (!function_exists('ftp_delete_file')) {
    function ftp_delete_file(string $remoteFile, ?string $connection = null): bool {
        return FTPConnection::getInstance()->delete($remoteFile, $connection);
    }
}

/**
 * List files via FTP
 *
 * Usage:
 * $files = ftp_list_files('/directory');
 *
 * @param string $directory Directory path
 * @param string|null $connection Connection name
 * @return array Array of file names
 */
if (!function_exists('ftp_list_files')) {
    function ftp_list_files(string $directory = '.', ?string $connection = null): array {
        return FTPConnection::getInstance()->listFiles($directory, $connection);
    }
}

/**
 * Create directory via FTP
 *
 * Usage:
 * ftp_create_directory('/new-folder');
 *
 * @param string $directory Directory path
 * @param string|null $connection Connection name
 * @return bool Success
 */
if (!function_exists('ftp_create_directory')) {
    function ftp_create_directory(string $directory, ?string $connection = null): bool {
        return FTPConnection::getInstance()->mkdir($directory, $connection);
    }
}

/**
 * Check if FTP file exists
 *
 * Usage:
 * if (ftp_file_exists('remote.txt')) { ... }
 *
 * @param string $remoteFile Remote file path
 * @param string|null $connection Connection name
 * @return bool True if file exists
 */
if (!function_exists('ftp_file_exists')) {
    function ftp_file_exists(string $remoteFile, ?string $connection = null): bool {
        return FTPConnection::getInstance()->exists($remoteFile, $connection);
    }
}

/**
 * Get FTP file size
 *
 * Usage:
 * $size = ftp_file_size('remote.txt');
 *
 * @param string $remoteFile Remote file path
 * @param string|null $connection Connection name
 * @return int File size in bytes
 */
if (!function_exists('ftp_file_size')) {
    function ftp_file_size(string $remoteFile, ?string $connection = null): int {
        return FTPConnection::getInstance()->size($remoteFile, $connection);
    }
}

/**
 * Test FTP connections health
 *
 * Usage:
 * $health = ftp_health_check();
 *
 * @return array Health status for all connections
 */
if (!function_exists('ftp_health_check')) {
    function ftp_health_check(): array {
        return FTPConnection::getInstance()->testAllConnections();
    }
}

/**
 * Get available FTP connections
 *
 * Usage:
 * $connections = ftp_connections();
 * // Returns: ['default', 'backup', 'cdn', ...]
 *
 * @return array Array of connection names
 */
if (!function_exists('ftp_connections')) {
    function ftp_connections(): array {
        return FTPConnection::getInstance()->getAvailableConnections();
    }
}
