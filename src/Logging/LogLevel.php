<?php

namespace Vireo\Framework\Logging;

/**
 * Log Levels (PSR-3 Compatible)
 *
 * Describes log levels as defined in RFC 5424
 */
class LogLevel
{
    const EMERGENCY = 'emergency'; // System is unusable
    const ALERT     = 'alert';     // Action must be taken immediately
    const CRITICAL  = 'critical';  // Critical conditions
    const ERROR     = 'error';     // Error conditions
    const WARNING   = 'warning';   // Warning conditions
    const NOTICE    = 'notice';    // Normal but significant condition
    const INFO      = 'info';      // Informational messages
    const DEBUG     = 'debug';     // Debug-level messages

    /**
     * Get all log levels in order of severity (highest to lowest)
     */
    public static function all(): array
    {
        return [
            self::EMERGENCY,
            self::ALERT,
            self::CRITICAL,
            self::ERROR,
            self::WARNING,
            self::NOTICE,
            self::INFO,
            self::DEBUG,
        ];
    }

    /**
     * Get severity level (0 = highest, 7 = lowest)
     */
    public static function getSeverity(string $level): int
    {
        $levels = self::all();
        $index = array_search($level, $levels);
        return $index !== false ? $index : 7;
    }

    /**
     * Check if a level should be logged based on minimum level
     */
    public static function shouldLog(string $level, string $minLevel): bool
    {
        return self::getSeverity($level) <= self::getSeverity($minLevel);
    }
}
