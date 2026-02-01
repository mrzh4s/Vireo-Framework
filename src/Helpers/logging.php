<?php

use Vireo\Framework\Logging\Logger;

/**
 * Logging Helper Functions
 * File: Framework/Helpers/logging.php
 *
 * Convenient helper functions for logging throughout the application
 */

// ============== MAIN LOGGING HELPERS ==============

/**
 * Get logger instance
 *
 * Usage:
 * logger()->info('User logged in');
 * logger('security')->warning('Failed login attempt');
 *
 * @param string|null $channel Channel name (null = default 'app')
 * @return Logger Logger instance
 */
if (!function_exists('logger')) {
    function logger(?string $channel = null): Logger {
        return $channel ? Logger::channel($channel) : Logger::getInstance();
    }
}

/**
 * Log an emergency message
 *
 * Usage:
 * log_emergency('System is down!');
 */
if (!function_exists('log_emergency')) {
    function log_emergency(string $message, array $context = [], ?string $channel = null): void {
        logger($channel)->emergency($message, $context);
    }
}

/**
 * Log an alert message
 *
 * Usage:
 * log_alert('Database connection lost');
 */
if (!function_exists('log_alert')) {
    function log_alert(string $message, array $context = [], ?string $channel = null): void {
        logger($channel)->alert($message, $context);
    }
}

/**
 * Log a critical message
 *
 * Usage:
 * log_critical('Application component unavailable');
 */
if (!function_exists('log_critical')) {
    function log_critical(string $message, array $context = [], ?string $channel = null): void {
        logger($channel)->critical($message, $context);
    }
}

/**
 * Log an error message
 *
 * Usage:
 * log_error('Failed to process payment', ['order_id' => 123]);
 */
if (!function_exists('log_error')) {
    function log_error(string $message, array $context = [], ?string $channel = null): void {
        logger($channel)->error($message, $context);
    }
}

/**
 * Log a warning message
 *
 * Usage:
 * log_warning('Disk space running low');
 */
if (!function_exists('log_warning')) {
    function log_warning(string $message, array $context = [], ?string $channel = null): void {
        logger($channel)->warning($message, $context);
    }
}

/**
 * Log a notice message
 *
 * Usage:
 * log_notice('User changed password');
 */
if (!function_exists('log_notice')) {
    function log_notice(string $message, array $context = [], ?string $channel = null): void {
        logger($channel)->notice($message, $context);
    }
}

/**
 * Log an info message
 *
 * Usage:
 * log_info('User {username} logged in', ['username' => 'john']);
 */
if (!function_exists('log_info')) {
    function log_info(string $message, array $context = [], ?string $channel = null): void {
        logger($channel)->info($message, $context);
    }
}

/**
 * Log a debug message
 *
 * Usage:
 * log_debug('Processing request', ['request_id' => 'abc123']);
 */
if (!function_exists('log_debug')) {
    function log_debug(string $message, array $context = [], ?string $channel = null): void {
        logger($channel)->debug($message, $context);
    }
}

// ============== SPECIALIZED LOGGING HELPERS ==============

/**
 * Log a query (for database queries)
 *
 * Usage:
 * log_query('SELECT * FROM users WHERE id = ?', [1], 0.05);
 */
if (!function_exists('log_query')) {
    function log_query(string $query, array $bindings = [], float $time = 0): void {
        if (app_debug()) {
            logger('database')->debug('Query executed', [
                'query' => $query,
                'bindings' => $bindings,
                'time' => $time . 'ms',
            ]);
        }
    }
}

/**
 * Log a performance metric
 *
 * Usage:
 * log_performance('api_call', 1.5, ['endpoint' => '/users']);
 */
if (!function_exists('log_performance')) {
    function log_performance(string $metric, float $value, array $context = []): void {
        logger('performance')->info($metric, array_merge($context, ['value' => $value]));
    }
}

/**
 * Log a security event
 *
 * Usage:
 * log_security('Failed login attempt', ['ip' => '192.168.1.1', 'username' => 'admin']);
 */
if (!function_exists('log_security')) {
    function log_security(string $event, array $context = []): void {
        logger('security')->warning($event, $context);
    }
}

/**
 * Log an API request
 *
 * Usage:
 * log_api_request('POST', '/api/users', 200, 0.5);
 */
if (!function_exists('log_api_request')) {
    function log_api_request(string $method, string $path, int $status, float $time): void {
        logger('api')->info('API Request', [
            'method' => $method,
            'path' => $path,
            'status' => $status,
            'time' => $time . 'ms',
        ]);
    }
}

/**
 * Log an exception
 *
 * Usage:
 * log_exception($exception, 'Failed to process payment');
 */
if (!function_exists('log_exception')) {
    function log_exception(\Throwable $exception, string $message = ''): void {
        $context = [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];

        $logMessage = $message ?: 'Exception occurred';
        logger()->error($logMessage, $context);
    }
}
