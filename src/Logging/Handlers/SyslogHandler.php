<?php

namespace Vireo\Framework\Logging\Handlers;

use Vireo\Framework\Logging\LogLevel;

/**
 * Syslog Handler
 *
 * Writes log messages to system log
 */
class SyslogHandler
{
    private string $ident;
    private int $facility;

    public function __construct(array $config)
    {
        $this->ident = $config['ident'] ?? 'vireo-framework';
        $this->facility = $config['facility'] ?? LOG_USER;

        openlog($this->ident, LOG_PID | LOG_ODELAY, $this->facility);
    }

    /**
     * Handle log record
     */
    public function handle(array $record): void
    {
        $priority = $this->getPriority($record['level']);
        $message = $this->format($record);

        syslog($priority, $message);
    }

    /**
     * Get syslog priority from log level
     */
    private function getPriority(string $level): int
    {
        return match ($level) {
            LogLevel::EMERGENCY => LOG_EMERG,
            LogLevel::ALERT => LOG_ALERT,
            LogLevel::CRITICAL => LOG_CRIT,
            LogLevel::ERROR => LOG_ERR,
            LogLevel::WARNING => LOG_WARNING,
            LogLevel::NOTICE => LOG_NOTICE,
            LogLevel::INFO => LOG_INFO,
            LogLevel::DEBUG => LOG_DEBUG,
            default => LOG_INFO,
        };
    }

    /**
     * Format log record
     */
    private function format(array $record): string
    {
        $channel = $record['channel'];
        $message = $record['message'];

        $formatted = "{$channel}: {$message}";

        if (!empty($record['context'])) {
            $formatted .= ' ' . json_encode($record['context']);
        }

        return $formatted;
    }

    /**
     * Close syslog connection
     */
    public function __destruct()
    {
        closelog();
    }
}
