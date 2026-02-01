<?php

if (!function_exists('dd')) {
    /**
     * Dump and Die function for debugging
     * 
     * Usage: dd($var1, $var2, ...);
     */
    function dd(...$args)
    {
        // Clear output buffer to remove any previous HTML
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Get backtrace to show where dd() was called
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        $file = isset($backtrace['file']) ? $backtrace['file'] : 'unknown';
        $line = isset($backtrace['line']) ? $backtrace['line'] : 'unknown';

        echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Debug</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: "Consolas", "Monaco", monospace; 
                background: #000;
                color: #ddd;
                padding: 20px;
                line-height: 1.6;
            }
            .dd-header {
                color: #888;
                font-size: 12px;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 1px solid #333;
            }
            .dd-toggle { 
                cursor: pointer; 
                user-select: none;
            }
            .dd-toggle:hover { opacity: 0.7; }
            .dd-arrow {
                display: inline-block;
                transition: transform 0.2s;
                margin-right: 5px;
            }
            .dd-arrow.expanded { transform: rotate(90deg); }
            .dd-hidden { 
                display: none; 
                margin-left: 20px;
                margin-top: 3px;
            }
            .dd-depth-0 { color: #ff6b6b; }
            .dd-depth-1 { color: #feca57; }
            .dd-depth-2 { color: #48dbfb; }
            .dd-depth-3 { color: #1dd1a1; }
            .dd-depth-4 { color: #ff9ff3; }
            .dd-depth-5 { color: #f368e0; }
            .dd-depth-6 { color: #00d2d3; }
            .dd-value { color: #aaa; }
            .dd-key { color: #54a0ff; }
        </style>
    </head>
    <body>
        <div class="dd-header">' . htmlspecialchars($file) . ':' . htmlspecialchars($line) . '</div>';

        foreach ($args as $index => $arg) {
            echo renderDump($arg, "root_" . $index, 0);
        }

        echo '<script>
            function ddToggle(id) {
                var el = document.getElementById(id);
                var arrow = document.getElementById("arrow_" + id);
                if (el.style.display === "none" || el.style.display === "") {
                    el.style.display = "block";
                    if (arrow) arrow.classList.add("expanded");
                } else {
                    el.style.display = "none";
                    if (arrow) arrow.classList.remove("expanded");
                }
            }
        </script>
    </body>
    </html>';

        die();
    }
}

if (!function_exists('renderDump')) {
    /**
     * Recursive function to render variable dump
     */
    function renderDump($var, $id, $depth = 0)
    {
        $depthClass = 'dd-depth-' . ($depth % 7);

        if (is_array($var)) {
            $count = count($var);
            $html = "<div>";
            $html .= "<span class='dd-toggle $depthClass' onclick=\"ddToggle('$id')\">";
            $html .= "<span class='dd-arrow' id='arrow_$id'>▶</span>";
            $html .= "Array($count)";
            $html .= "</span>";
            $html .= "<div id='$id' class='dd-hidden'>";

            foreach ($var as $k => $v) {
                $childId = $id . "_" . preg_replace('/[^a-zA-Z0-9]/', '_', $k);
                $html .= "<div>";
                $html .= "<span class='dd-key'>[" . htmlspecialchars($k) . "]</span> => ";
                $html .= renderDump($v, $childId, $depth + 1);
                $html .= "</div>";
            }

            $html .= "</div></div>";
            return $html;
        } elseif (is_object($var)) {
            $className = get_class($var);
            $arr = (array)$var;
            $count = count($arr);

            $html = "<div>";
            $html .= "<span class='dd-toggle $depthClass' onclick=\"ddToggle('$id')\">";
            $html .= "<span class='dd-arrow' id='arrow_$id'>▶</span>";
            $html .= htmlspecialchars($className) . "($count)";
            $html .= "</span>";
            $html .= "<div id='$id' class='dd-hidden'>";

            foreach ($arr as $k => $v) {
                $childId = $id . "_" . preg_replace('/[^a-zA-Z0-9]/', '_', $k);
                $html .= "<div>";
                $html .= "<span class='dd-key'>[" . htmlspecialchars($k) . "]</span> => ";
                $html .= renderDump($v, $childId, $depth + 1);
                $html .= "</div>";
            }

            $html .= "</div></div>";
            return $html;
        } elseif (is_string($var)) {
            return "<span class='dd-value'>\"" . htmlspecialchars($var) . "\"</span>";
        } elseif (is_bool($var)) {
            return "<span class='dd-value'>" . ($var ? 'true' : 'false') . "</span>";
        } elseif (is_null($var)) {
            return "<span class='dd-value'>null</span>";
        } else {
            return "<span class='dd-value'>" . htmlspecialchars(var_export($var, true)) . "</span>";
        }
    }
}

// ============== ADDITIONAL DEBUG HELPERS ==============

if (!function_exists('dump')) {
    /**
     * Dump the given variables without ending the script
     *
     * Usage:
     * dump($user);
     * dump($user, $posts);
     */
    function dump(...$vars): void {
        foreach ($vars as $var) {
            if (php_sapi_name() === 'cli') {
                // CLI output
                var_dump($var);
            } else {
                // Web output with better formatting
                echo '<pre style="background: #1e1e1e; color: #d4d4d4; padding: 1rem; margin: 0.5rem; border-radius: 4px; overflow-x: auto;">';
                var_dump($var);
                echo '</pre>';
            }
        }
    }
}

if (!function_exists('pr')) {
    /**
     * Pretty print the given variables
     *
     * Usage:
     * pr($user);
     * pr($user, $posts);
     */
    function pr(...$vars): void {
        foreach ($vars as $var) {
            if (php_sapi_name() === 'cli') {
                print_r($var);
                echo PHP_EOL;
            } else {
                echo '<pre style="background: #1e1e1e; color: #d4d4d4; padding: 1rem; margin: 0.5rem; border-radius: 4px; overflow-x: auto;">';
                print_r($var);
                echo '</pre>';
            }
        }
    }
}

// ============== BACKTRACE HELPERS ==============

if (!function_exists('debug_trace')) {
    /**
     * Get a formatted debug backtrace
     *
     * Usage:
     * debug_trace();
     * debug_trace(5); // Limit to 5 frames
     */
    function debug_trace(int $limit = 10): array {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit + 1);
        array_shift($trace); // Remove this function from trace

        return array_map(function ($frame) {
            return [
                'file' => $frame['file'] ?? 'unknown',
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? '',
                'class' => $frame['class'] ?? '',
                'type' => $frame['type'] ?? '',
            ];
        }, $trace);
    }
}

if (!function_exists('debug_trace_die')) {
    /**
     * Print a formatted debug backtrace and die
     *
     * Usage:
     * debug_trace_die();
     * debug_trace_die(5); // Limit to 5 frames
     */
    function debug_trace_die(int $limit = 10): void {
        $trace = debug_trace($limit);

        if (php_sapi_name() === 'cli') {
            echo "Debug Backtrace:\n";
            foreach ($trace as $i => $frame) {
                $location = "{$frame['class']}{$frame['type']}{$frame['function']}";
                echo sprintf("#%d %s at %s:%d\n", $i, $location, $frame['file'], $frame['line']);
            }
        } else {
            echo '<pre style="background: #1e1e1e; color: #d4d4d4; padding: 1rem; margin: 0.5rem; border-radius: 4px; overflow-x: auto;">';
            echo "<strong>Debug Backtrace:</strong>\n\n";
            foreach ($trace as $i => $frame) {
                $location = "{$frame['class']}{$frame['type']}{$frame['function']}";
                echo sprintf("#%d %s\n    at %s:%d\n\n", $i, $location, $frame['file'], $frame['line']);
            }
            echo '</pre>';
        }

        exit(1);
    }
}

// ============== ENVIRONMENT HELPERS ==============

if (!function_exists('app_debug')) {
    /**
     * Check if application is in debug mode
     *
     * Usage:
     * if (app_debug()) { dump($query); }
     */
    function app_debug(): bool {
        return (bool) env('APP_DEBUG', false);
    }
}

if (!function_exists('is_cli')) {
    /**
     * Check if running in CLI mode
     *
     * Usage:
     * if (is_cli()) { echo "Running in CLI\n"; }
     */
    function is_cli(): bool {
        return php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg';
    }
}

// ============== MEMORY AND PERFORMANCE ==============

if (!function_exists('memory_usage')) {
    /**
     * Get current memory usage in human-readable format
     *
     * Usage:
     * echo memory_usage(); // "5.2 MB"
     */
    function memory_usage(bool $real = true): string {
        $bytes = memory_get_usage($real);
        return format_bytes($bytes);
    }
}

if (!function_exists('peak_memory')) {
    /**
     * Get peak memory usage in human-readable format
     *
     * Usage:
     * echo peak_memory(); // "10.5 MB"
     */
    function peak_memory(bool $real = true): string {
        $bytes = memory_get_peak_usage($real);
        return format_bytes($bytes);
    }
}

if (!function_exists('format_bytes')) {
    /**
     * Format bytes into human-readable format
     *
     * Usage:
     * echo format_bytes(1024); // "1 KB"
     */
    function format_bytes(int $bytes, int $precision = 2): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

if (!function_exists('benchmark')) {
    /**
     * Measure execution time of a callback
     *
     * Usage:
     * $time = benchmark(function() {
     *     // code to benchmark
     * });
     * echo "Took {$time}ms";
     */
    function benchmark(callable $callback): float {
        $start = microtime(true);
        $callback();
        $end = microtime(true);

        return round(($end - $start) * 1000, 2); // Return milliseconds
    }
}

// ============== TYPE HELPERS ==============

if (!function_exists('get_type')) {
    /**
     * Get the type of a variable in a readable format
     *
     * Usage:
     * echo get_type($var); // "string", "array", "User object", etc.
     */
    function get_type($var): string {
        if (is_object($var)) {
            return get_class($var) . ' object';
        }

        return gettype($var);
    }
}

if (!function_exists('var_info')) {
    /**
     * Get detailed information about a variable
     *
     * Usage:
     * var_info($user);
     */
    function var_info($var): array {
        $info = [
            'type' => get_type($var),
            'value' => $var,
        ];

        if (is_array($var)) {
            $info['count'] = count($var);
            $info['keys'] = array_keys($var);
        } elseif (is_object($var)) {
            $info['methods'] = get_class_methods($var);
            $info['properties'] = get_object_vars($var);
        } elseif (is_string($var)) {
            $info['length'] = strlen($var);
        }

        return $info;
    }
}

// ============== CLASS AND FUNCTION HELPERS ==============

if (!function_exists('class_methods')) {
    /**
     * Get all methods of a class or object
     *
     * Usage:
     * class_methods($user);
     * class_methods(User::class);
     */
    function class_methods($classOrObject): array {
        if (is_string($classOrObject)) {
            return get_class_methods($classOrObject) ?? [];
        }

        return get_class_methods($classOrObject) ?? [];
    }
}

if (!function_exists('class_properties')) {
    /**
     * Get all properties of a class or object
     *
     * Usage:
     * class_properties($user);
     * class_properties(User::class);
     */
    function class_properties($classOrObject): array {
        if (is_object($classOrObject)) {
            return get_object_vars($classOrObject);
        }

        return get_class_vars($classOrObject) ?? [];
    }
}

if (!function_exists('uses_trait')) {
    /**
     * Check if a class uses a specific trait
     *
     * Usage:
     * if (uses_trait($user, Timestampable::class)) { ... }
     */
    function uses_trait($classOrObject, string $trait): bool {
        $traits = class_uses_recursive($classOrObject);
        return in_array($trait, $traits, true);
    }
}

if (!function_exists('class_uses_recursive')) {
    /**
     * Get all traits used by a class (recursively)
     *
     * Usage:
     * $traits = class_uses_recursive($user);
     */
    function class_uses_recursive($classOrObject): array {
        if (is_object($classOrObject)) {
            $classOrObject = get_class($classOrObject);
        }

        $results = [];

        foreach (array_reverse(class_parents($classOrObject) ?: []) + [$classOrObject => $classOrObject] as $class) {
            $results += trait_uses_recursive($class);
        }

        return array_unique($results);
    }
}

if (!function_exists('trait_uses_recursive')) {
    /**
     * Get all traits used by a trait (recursively)
     */
    function trait_uses_recursive(string $trait): array {
        $traits = class_uses($trait) ?: [];

        foreach ($traits as $usedTrait) {
            $traits += trait_uses_recursive($usedTrait);
        }

        return $traits;
    }
}

// ============== SQL DEBUGGING ==============

if (!function_exists('dump_sql')) {
    /**
     * Dump the SQL query and bindings
     *
     * Usage:
     * dump_sql('SELECT * FROM users WHERE id = ?', [1]);
     */
    function dump_sql(string $query, array $bindings = []): void {
        $output = $query;

        // Replace placeholders with actual values
        foreach ($bindings as $binding) {
            $value = is_string($binding) ? "'{$binding}'" : $binding;
            $output = preg_replace('/\?/', (string) $value, $output, 1);
        }

        if (php_sapi_name() === 'cli') {
            echo "SQL Query:\n{$output}\n\n";
        } else {
            echo '<pre style="background: #1e1e1e; color: #d4d4d4; padding: 1rem; margin: 0.5rem; border-radius: 4px; overflow-x: auto;">';
            echo "<strong>SQL Query:</strong>\n{$output}";
            echo '</pre>';
        }
    }
}

if (!function_exists('dd_sql')) {
    /**
     * Dump the SQL query and bindings, then die
     *
     * Usage:
     * dd_sql('SELECT * FROM users WHERE id = ?', [1]);
     */
    function dd_sql(string $query, array $bindings = []): void {
        dump_sql($query, $bindings);
        exit(1);
    }
}

// ============== REQUEST DEBUGGING ==============

if (!function_exists('dump_request')) {
    /**
     * Dump all request data
     *
     * Usage:
     * dump_request();
     */
    function dump_request(): void {
        $data = [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            'get' => $_GET,
            'post' => $_POST,
            'files' => $_FILES,
            'headers' => function_exists('getallheaders') ? getallheaders() : [],
            'server' => $_SERVER,
        ];

        dump($data);
    }
}

if (!function_exists('dd_request')) {
    /**
     * Dump request data and die
     *
     * Usage:
     * dd_request();
     */
    function dd_request(): void {
        dump_request();
        exit(1);
    }
}
