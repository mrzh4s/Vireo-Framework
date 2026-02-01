<?php

namespace Framework\Logging\Handlers;

use DateTime;

/**
 * File Handler
 *
 * Writes log messages to files with automatic rotation
 */
class FileHandler
{
    private string $path;
    private string $filename;
    private int $maxSize;
    private int $maxFiles;

    public function __construct(array $config)
    {
        $this->path = $config['path'] ?? ROOT_PATH . '/storage/logs';
        $this->filename = $config['filename'] ?? 'app.log';
        $this->maxSize = $config['max_size'] ?? 10 * 1024 * 1024; // 10 MB
        $this->maxFiles = $config['max_files'] ?? 5;

        $this->ensureLogDirectory();
    }

    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory(): void
    {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    /**
     * Handle log record
     */
    public function handle(array $record): void
    {
        $logFile = $this->path . '/' . $this->filename;

        // Check if rotation is needed
        if (file_exists($logFile) && filesize($logFile) >= $this->maxSize) {
            $this->rotate($logFile);
        }

        $message = $this->format($record);
        file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX);
    }

    /**
     * Format log record
     */
    private function format(array $record): string
    {
        $datetime = $record['datetime']->format('Y-m-d H:i:s');
        $level = strtoupper($record['level']);
        $channel = $record['channel'];
        $message = $record['message'];

        $line = "[{$datetime}] {$channel}.{$level}: {$message}";

        // Add context if available
        if (!empty($record['context'])) {
            $line .= ' ' . json_encode($record['context'], JSON_UNESCAPED_SLASHES);
        }

        // Add extra data if in debug mode
        if (app_debug() && !empty($record['extra'])) {
            $line .= ' ' . json_encode($record['extra'], JSON_UNESCAPED_SLASHES);
        }

        return $line . PHP_EOL;
    }

    /**
     * Rotate log files
     */
    private function rotate(string $logFile): void
    {
        // Delete oldest file if we've reached max files
        $oldestFile = $logFile . '.' . $this->maxFiles;
        if (file_exists($oldestFile)) {
            unlink($oldestFile);
        }

        // Shift existing files
        for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
            $currentFile = $logFile . '.' . $i;
            $nextFile = $logFile . '.' . ($i + 1);

            if (file_exists($currentFile)) {
                rename($currentFile, $nextFile);
            }
        }

        // Rename current log file
        if (file_exists($logFile)) {
            rename($logFile, $logFile . '.1');
        }
    }
}
