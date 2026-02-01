<?php
/**
 * Inertia.js Adapter for Vireo Framework (NO COMPOSER REQUIRED)
 * File: apps/core/Inertia.php
 *
 * This is a lightweight implementation of Inertia.js protocol
 * Works exactly like Laravel's Inertia but without external dependencies
 *
 * Features:
 * - Bridges PHP backend with Vue/React/Svelte frontend
 * - No API needed - uses controllers directly
 * - SSR support (optional)
 * - Shared props across all responses
 * - Asset versioning for cache busting
 *
 * Usage:
 *   return Inertia::render('Dashboard', ['users' => $users]);
 */

namespace Vireo\Framework\View;
use Exception;

class Inertia {
    /**
     * Shared props that will be included in all Inertia responses
     */
    private static $sharedProps = [];

    /**
     * Asset version for cache busting
     */
    private static $version = null;

    /**
     * Root view template (default: 'app')
     */
    private static $rootView = 'app';

    /**
     * SSR server URL (if SSR is enabled)
     */
    private static $ssrUrl = 'http://localhost:13714';

    /**
     * SSR enabled flag
     */
    private static $ssrEnabled = false;

    /**
     * Render an Inertia response
     *
     * @param string $component Vue/React component name (e.g., 'Dashboard', 'Users/Index')
     * @param array $props Data to pass to the component
     * @return void
     */
    public static function render($component, $props = []) {
        // Auto-share session data (errors, flash messages, old input, auth)
        $sessionProps = self::getSessionProps();

        // Merge: session props -> shared props -> page-specific props
        $allProps = array_merge($sessionProps, self::$sharedProps, $props);

        // Evaluate lazy props (callables)
        $allProps = self::evaluateLazyProps($allProps);

        // Create page object
        $page = [
            'component' => $component,
            'props' => $allProps,
            'url' => self::getCurrentUrl(),
            'version' => self::getAssetVersion()
        ];

        // Clear one-time session data (flash messages, errors)
        self::clearFlashData();

        // Check if this is an Inertia XHR request
        if (self::isInertiaRequest()) {
            self::sendInertiaResponse($page);
        } else {
            // Initial page load - render HTML
            self::sendHtmlResponse($page);
        }
    }

    /**
     * Share data with all Inertia responses
     *
     * @param string|array $key Key name or array of key-value pairs
     * @param mixed $value Value (ignored if $key is array)
     * @return void
     */
    public static function share($key, $value = null) {
        if (is_array($key)) {
            self::$sharedProps = array_merge(self::$sharedProps, $key);
        } else {
            self::$sharedProps[$key] = $value;
        }
    }

    /**
     * Set asset version for cache busting
     *
     * @param string|callable $version Version string or callable
     * @return void
     */
    public static function version($version) {
        self::$version = $version;
    }

    /**
     * Lazy prop - only evaluated on partial reloads
     *
     * @param callable $callback
     * @return array
     */
    public static function lazy($callback) {
        return [
            '__inertia_lazy' => true,
            'callback' => $callback
        ];
    }

    /**
     * Inertia redirect (external redirect)
     *
     * @param string $url URL to redirect to
     * @return void
     */
    public static function location($url) {
        if (self::isInertiaRequest()) {
            // For Inertia requests, use 409 status with X-Inertia-Location header
            header('X-Inertia-Location: ' . $url);
            http_response_code(409);
            exit;
        }

        // Regular redirect
        header('Location: ' . $url);
        exit;
    }

    /**
     * Enable Server-Side Rendering
     *
     * @param string $url SSR server URL
     * @return void
     */
    public static function enableSsr($url = 'http://localhost:13714') {
        self::$ssrEnabled = true;
        self::$ssrUrl = $url;
    }

    /**
     * Set root view template
     *
     * @param string $view View name
     * @return void
     */
    public static function setRootView($view) {
        self::$rootView = $view;
    }

    // ============== PRIVATE METHODS ==============

    /**
     * Check if current request is an Inertia XHR request
     *
     * @return bool
     */
    private static function isInertiaRequest() {
        return isset($_SERVER['HTTP_X_INERTIA']) && $_SERVER['HTTP_X_INERTIA'] === 'true';
    }

    /**
     * Check if this is a partial reload (only some props requested)
     *
     * @return bool
     */
    private static function isPartialReload() {
        return isset($_SERVER['HTTP_X_INERTIA_PARTIAL_DATA']) &&
               isset($_SERVER['HTTP_X_INERTIA_PARTIAL_COMPONENT']);
    }

    /**
     * Get requested partial props
     *
     * @return array
     */
    private static function getPartialProps() {
        if (!self::isPartialReload()) {
            return [];
        }

        $only = $_SERVER['HTTP_X_INERTIA_PARTIAL_DATA'] ?? '';
        return array_filter(explode(',', $only));
    }

    /**
     * Send Inertia JSON response (for XHR requests)
     *
     * @param array $page Page object
     * @return void
     */
    private static function sendInertiaResponse($page) {
        // Handle partial reloads
        if (self::isPartialReload()) {
            $only = self::getPartialProps();
            if (!empty($only)) {
                $page['props'] = array_intersect_key(
                    $page['props'],
                    array_flip($only)
                );
            }
        }

        // Set headers
        header('Content-Type: application/json');
        header('Vary: Accept');
        header('X-Inertia: true');

        // Output JSON
        echo json_encode($page);
        exit;
    }

    /**
     * Send HTML response (for initial page load)
     *
     * @param array $page Page object
     * @return void
     */
    private static function sendHtmlResponse($page) {
        // Try SSR first (if enabled)
        if (self::$ssrEnabled) {
            $html = self::renderSsr($page);
            if ($html !== null) {
                echo $html;
                exit;
            }
        }

        // Fallback to client-side rendering
        $pageJson = htmlspecialchars(json_encode($page), ENT_QUOTES, 'UTF-8');

        // Render root view
        if (function_exists('view')) {
            echo view(self::$rootView, ['page' => $pageJson]);
        } else {
            // Fallback if view helper not available
            self::renderDefaultTemplate($pageJson);
        }
        exit;
    }

    /**
     * Server-Side Rendering via Node.js server
     *
     * @param array $page Page object
     * @return string|null Rendered HTML or null on failure
     */
    private static function renderSsr($page) {
        try {
            $ch = curl_init(self::$ssrUrl);

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($page),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: text/html'
                ],
                CURLOPT_TIMEOUT => 1, // 1 second timeout
            ]);

            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpCode === 200 && $html) {
                return $html;
            }

            return null;

        } catch (Exception $e) {
            error_log("Inertia SSR failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Render default template (if view helper not available)
     *
     * @param string $pageJson JSON-encoded page object
     * @return void
     */
    private static function renderDefaultTemplate($pageJson) {
        $appName = app('name') ?? 'Vireo Framework';
        $appUrl = app('url') ?? '';
        $isLocal = app('env') === 'local';

        echo "<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>{$appName}</title>
    ";

        if ($isLocal) {
            // Vite dev server
            echo "<script type=\"module\" src=\"http://localhost:5173/@vite/client\"></script>
    <script type=\"module\" src=\"http://localhost:5173/src/main.js\"></script>";
        } else {
            // Production assets
            echo "<link rel=\"stylesheet\" href=\"{$appUrl}/assets/css/app.css\">
    <script type=\"module\" src=\"{$appUrl}/assets/js/app.js\"></script>";
        }

        echo "
</head>
<body>
    <div id=\"app\" data-page='{$pageJson}'></div>
</body>
</html>";
    }

    /**
     * Evaluate lazy props
     *
     * @param array $props
     * @return array
     */
    private static function evaluateLazyProps($props) {
        $evaluated = [];

        foreach ($props as $key => $value) {
            // Check if it's a lazy prop
            if (is_array($value) && isset($value['__inertia_lazy'])) {
                // Only evaluate if specifically requested in partial reload
                $only = self::getPartialProps();
                if (empty($only) || in_array($key, $only)) {
                    $evaluated[$key] = call_user_func($value['callback']);
                }
            } elseif (is_callable($value)) {
                // Callable props are evaluated immediately
                $evaluated[$key] = call_user_func($value);
            } else {
                $evaluated[$key] = $value;
            }
        }

        return $evaluated;
    }

    /**
     * Get current URL
     *
     * @return string
     */
    private static function getCurrentUrl() {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    /**
     * Get asset version
     *
     * @return string
     */
    private static function getAssetVersion() {
        if (self::$version === null) {
            return '1.0';
        }

        if (is_callable(self::$version)) {
            return call_user_func(self::$version);
        }

        return self::$version;
    }

    /**
     * Get session props (errors, flash messages, old input, auth)
     * These are automatically shared with all Inertia responses
     *
     * @return array
     */
    private static function getSessionProps() {
        if (!isset($_SESSION)) {
            session_start();
        }

        $props = [];

        // Validation errors
        if (isset($_SESSION['errors'])) {
            $props['errors'] = $_SESSION['errors'];
        }

        // Flash messages
        $flashKeys = ['success', 'error', 'warning', 'info', 'message'];
        foreach ($flashKeys as $key) {
            if (isset($_SESSION[$key])) {
                $props['flash'][$key] = $_SESSION[$key];
            }
        }

        // Old input (for form repopulation after validation errors)
        if (isset($_SESSION['old'])) {
            $props['old'] = $_SESSION['old'];
        }

        // Authenticated user (if available)
        if (isset($_SESSION['authenticated']) && $_SESSION['authenticated']) {
            $props['auth'] = [
                'user' => $_SESSION['user'] ?? null,
                'authenticated' => true
            ];
        } else {
            $props['auth'] = [
                'user' => null,
                'authenticated' => false
            ];
        }

        // CSRF token (if available)
        if (function_exists('csrf_token')) {
            $props['csrf_token'] = csrf_token();
        } elseif (isset($_SESSION['csrf_token'])) {
            $props['csrf_token'] = $_SESSION['csrf_token'];
        }

        return $props;
    }

    /**
     * Clear one-time flash data from session
     * Errors, flash messages, and old input are cleared after being shared
     *
     * @return void
     */
    private static function clearFlashData() {
        if (!isset($_SESSION)) {
            return;
        }

        // Clear validation errors
        unset($_SESSION['errors']);

        // Clear flash messages
        $flashKeys = ['success', 'error', 'warning', 'info', 'message'];
        foreach ($flashKeys as $key) {
            unset($_SESSION[$key]);
        }

        // Clear old input
        unset($_SESSION['old']);
    }

    /**
     * Flash a message to the session
     * Helper method for controllers to flash messages
     *
     * @param string $key Flash key (success, error, warning, info)
     * @param string $message Message content
     * @return void
     */
    public static function flash($key, $message) {
        if (!isset($_SESSION)) {
            session_start();
        }

        $_SESSION[$key] = $message;
    }

    /**
     * Flash errors to session
     * Helper method for validation errors
     *
     * @param array $errors Validation errors
     * @return void
     */
    public static function flashErrors($errors) {
        if (!isset($_SESSION)) {
            session_start();
        }

        $_SESSION['errors'] = $errors;
    }

    /**
     * Flash old input to session
     * Helper method for form repopulation
     *
     * @param array $input Old input data
     * @return void
     */
    public static function flashOld($input) {
        if (!isset($_SESSION)) {
            session_start();
        }

        $_SESSION['old'] = $input;
    }
}
