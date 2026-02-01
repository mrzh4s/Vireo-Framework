<?php

namespace Vireo\Framework\Cache\SessionHandlers;

use SessionHandlerInterface;

/**
 * FileSessionHandler - File-based session storage handler
 *
 * Wrapper for PHP's default file-based session handler.
 * This is mostly for consistency and potential future customization.
 */
class FileSessionHandler implements SessionHandlerInterface
{
    /**
     * Session save path
     */
    private string $savePath;

    /**
     * Session lifetime in seconds
     */
    private int $ttl;

    /**
     * Constructor
     *
     * @param array $config Configuration options
     */
    public function __construct(array $config)
    {
        $rootPath = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 3);
        $this->savePath = $config['path'] ?? $rootPath . '/storage/sessions';
        $this->ttl = $config['lifetime'] ?? 7200;

        // Create sessions directory if it doesn't exist
        if (!is_dir($this->savePath)) {
            mkdir($this->savePath, 0755, true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function open(string $path, string $name): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $id): string|false
    {
        $file = $this->savePath . '/sess_' . $id;

        if (!file_exists($file)) {
            return '';
        }

        $data = file_get_contents($file);
        return $data !== false ? $data : '';
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $id, string $data): bool
    {
        $file = $this->savePath . '/sess_' . $id;
        return file_put_contents($file, $data, LOCK_EX) !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(string $id): bool
    {
        $file = $this->savePath . '/sess_' . $id;

        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc(int $max_lifetime): int|false
    {
        $files = glob($this->savePath . '/sess_*');
        $deleted = 0;

        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            if (filemtime($file) + $max_lifetime < time()) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }
}
