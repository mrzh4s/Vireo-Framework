<?php

namespace Framework\Logging;

use Exception;

/**
 * Logger
 *
 * PSR-3 compatible logging system with multiple handlers and channels
 *
 * Features:
 * - Multiple log levels (emergency, alert, critical, error, warning, notice, info, debug)
 * - Multiple handlers (file, database, syslog, email, slack)
 * - Context data support
 * - Channel support (app, security, performance, etc.)
 * - Automatic log rotation
 */
class Logger
{
    /**
     * Singleton instance
     */
    private static ?Logger $instance = null;

    /**
     * Log handlers
     */
    private array $handlers = [];

    /**
     * Logger channel name
     */
    private string $channel;

    /**
     * Minimum log level
     */
    private string $minLevel;

    /**
     * Logger configuration
     */
    private array $config;

    /**
     * Create logger instance
     */
    private function __construct(string $channel = 'app', ?array $config = null)
    {
        $this->channel = $channel;
        $this->config = $config ?? $this->loadConfig();
        $this->minLevel = $this->config['min_level'] ?? LogLevel::DEBUG;
        $this->initializeHandlers();
    }

    /**
     * Get logger instance (singleton)
     */
    public static function getInstance(string $channel = 'app'): Logger
    {
        if (self::$instance === null || self::$instance->channel !== $channel) {
            self::$instance = new self($channel);
        }
        return self::$instance;
    }

    /**
     * Create a new logger for a specific channel
     */
    public static function channel(string $channel): Logger
    {
        return new self($channel);
    }

    /**
     * Load logging configuration
     */
    private function loadConfig(): array
    {
        $configPath = ROOT_PATH . '/Config/Logging.php';
        if (file_exists($configPath)) {
            return require $configPath;
        }

        // Default configuration
        return [
            'default' => 'file',
            'min_level' => LogLevel::DEBUG,
            'handlers' => [
                'file' => [
                    'driver' => 'file',
                    'path' => ROOT_PATH . '/storage/logs',
                    'filename' => 'app.log',
                    'max_size' => 10 * 1024 * 1024, // 10 MB
                    'max_files' => 5,
                ],
            ],
        ];
    }

    /**
     * Initialize log handlers
     */
    private function initializeHandlers(): void
    {
        $defaultHandler = $this->config['default'] ?? 'file';
        $handlers = $this->config['handlers'] ?? [];

        if (isset($handlers[$defaultHandler])) {
            $handlerConfig = $handlers[$defaultHandler];
            $this->addHandler($this->createHandler($handlerConfig));
        }
    }

    /**
     * Create a handler instance
     */
    private function createHandler(array $config)
    {
        $driver = $config['driver'] ?? 'file';

        return match ($driver) {
            'file' => new Handlers\FileHandler($config),
            'database' => new Handlers\DatabaseHandler($config),
            'syslog' => new Handlers\SyslogHandler($config),
            default => new Handlers\FileHandler($config),
        };
    }

    /**
     * Add a handler
     */
    public function addHandler($handler): void
    {
        $this->handlers[] = $handler;
    }

    /**
     * Log a message
     */
    public function log(string $level, string $message, array $context = []): void
    {
        // Check if level should be logged
        if (!LogLevel::shouldLog($level, $this->minLevel)) {
            return;
        }

        $record = [
            'channel' => $this->channel,
            'level' => $level,
            'message' => $this->interpolate($message, $context),
            'context' => $context,
            'datetime' => new \DateTime(),
            'extra' => $this->getExtraData(),
        ];

        foreach ($this->handlers as $handler) {
            try {
                $handler->handle($record);
            } catch (Exception $e) {
                // Fail silently to avoid breaking the application
                error_log("Logger handler failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Interpolate context values into message placeholders
     */
    private function interpolate(string $message, array $context = []): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }
        return strtr($message, $replace);
    }

    /**
     * Get extra data for log record
     */
    private function getExtraData(): array
    {
        return [
            'memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'request_id' => $_SERVER['UNIQUE_ID'] ?? uniqid('req_', true),
        ];
    }

    // ==================== PSR-3 Interface Methods ====================

    /**
     * System is unusable
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately
     */
    public function alert(string $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Normal but significant events
     */
    public function notice(string $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }
}
