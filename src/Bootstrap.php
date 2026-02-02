<?php
/**
 * Vireo Framework Bootstrap with Auto-Discovery
 * File: Framework/Bootstrap.php
 *
 * Features:
 * - Auto-discovers Framework classes with proper namespaces
 * - Auto-discovers helpers in Framework/Helpers/
 * - Maintains proper loading order and dependencies
 * - Uses PSR-4 autoloading for Features and other components
 */

namespace Vireo\Framework;

use Exception;
use Vireo\Framework\Database\Migration;
use Vireo\Framework\View\Blade;
use Vireo\Framework\View\Inertia;

/**
 * Define ROOT_PATH if not already defined by the application.
 * ROOT_PATH should point to the application's root directory.
 *
 * Detection order:
 * 1. Already defined by application (preferred)
 * 2. Auto-detect from vendor directory structure
 * 3. Fall back to current working directory
 */
if (!defined('ROOT_PATH')) {
    // Try to detect from vendor directory (package installed via Composer)
    $vendorDir = dirname(__DIR__, 4); // Go up from src/Bootstrap.php -> vireo/framework -> vendor -> app root
    if (file_exists($vendorDir . '/vendor/autoload.php')) {
        define('ROOT_PATH', $vendorDir);
    } elseif (file_exists(dirname(__DIR__, 2) . '/vendor/autoload.php')) {
        // Framework is at root level (development mode)
        define('ROOT_PATH', dirname(__DIR__, 2));
    } else {
        // Fall back to current working directory
        define('ROOT_PATH', getcwd());
    }
}

/**
 * Bootstrap Class with Namespace-Aware Auto-Discovery
 */
class Bootstrap {
    private static $loadedHelpers = [];
    private static $debug = false;

    // Helper files load order (dependency-aware)
    private static $helperOrder = [
        'env',           // Environment helpers first
        'config',        // Configuration helpers
        'cache',         // Cache helpers (before session for potential session caching)
        'session',       // Session helpers
        'cookie',        // Cookie helpers
        'security',      // Security helpers (CSRF, etc)
        'validation',    // Validation helpers
        'db',            // Database connection helpers
        'storage',       // Storage helpers
        'orm',           // ORM/Query Builder helpers
        'schema',        // Schema Builder helpers
        'migration',     // Migration helpers
        'permission',    // Permission helpers
        'activity',      // Activity logging helpers
        'traffic',       // Traffic logging helpers
        'logging',       // Logging helpers
        'http',          // HTTP/Curl helpers
        'router',        // Routing helpers
        'view',          // View/Asset helpers
        'inertia',       // Inertia helpers
        'debug',         // Debug helpers
        'cli',           // CLI/Console helpers
    ];

    /**
     * Main bootstrap method
     */
    public static function boot() {
        try {
            // Initialize debug mode
            self::$debug = (function_exists('env') && env('APP_DEBUG') === 'true');

            // 1. Load helpers (classes are autoloaded by Composer)
            self::loadHelpers();

            // 2. Auto-discover additional helpers
            self::autoDiscoverHelpers();

            // 3. Auto-discover Feature helpers
            self::autoDiscoverFeatureHelpers();

            // 4. Initialize framework
            self::initializeFramework();

            // 5. Run auto-migrations
            self::runAutoMigrations();

            // 6. Initialize view engine
            self::initializeViewEngine();

            // 7. Share Inertia data
            self::shareInertiaData();

            // Mark as loaded
            define('VIREO_FRAMEWORK_LOADED', true);

            // Log bootstrap status if debug mode is enabled
            $isDebug = (function_exists('env') && env('APP_DEBUG') === 'true');

            if ($isDebug) {
                self::logBootstrapStatus();
            }

        } catch (Exception $e) {
            self::handleBootstrapError($e);
        }
    }

    /**
     * Load helpers in dependency order
     */
    private static function loadHelpers() {
        $helpersDir = ROOT_PATH . '/Framework/Helpers';

        if (!is_dir($helpersDir)) {
            logger('app')->critical("Helpers directory not found", [
                'path' => $helpersDir
            ]);
            return;
        }

        // Load helpers in dependency order
        foreach (self::$helperOrder as $helperName) {
            self::loadHelperFile($helpersDir, $helperName);
        }
    }

    /**
     * Auto-discover additional helpers not in load order
     */
    private static function autoDiscoverHelpers() {
        $helpersDir = ROOT_PATH . '/Framework/Helpers';

        if (!is_dir($helpersDir)) {
            return;
        }

        $helperFiles = glob($helpersDir . '/*.php');

        foreach ($helperFiles as $helperFile) {
            $helperName = basename($helperFile, '.php');

            // Skip if already loaded
            if (in_array($helperName, self::$loadedHelpers)) {
                continue;
            }

            self::loadHelperFileDirectly($helperFile, $helperName);
        }
    }

    /**
     * Auto-discover and load helper files from Features directories
     *
     * Scans feature directories recursively to find all Helpers folders:
     * - Features/{Feature}/Helpers (direct feature helpers)
     * - Features/{Feature}/{SubFeature}/Helpers (e.g., Auth/Login/Helpers)
     * - Features/{Feature}/Shared/Helpers (e.g., Auth/Shared/Helpers)
     *
     * Feature helpers are tracked as "{Feature}:{SubFeature}:{helper}" or "{Feature}:{helper}"
     */
    private static function autoDiscoverFeatureHelpers() {
        $featuresPath = ROOT_PATH . '/Features';

        // Exit early if Features directory doesn't exist
        if (!is_dir($featuresPath)) {
            if (self::$debug) {
                logger('app')->debug('Features directory not found, skipping feature helper discovery', [
                    'path' => $featuresPath
                ]);
            }
            return;
        }

        $discoveredCount = self::scanNestedFeatureHelpers($featuresPath);

        if (self::$debug && $discoveredCount > 0) {
            logger('app')->debug('Feature helper discovery complete', [
                'helpers_loaded' => $discoveredCount
            ]);
        }
    }

    /**
     * Recursively scan nested feature structure for Helpers directories
     *
     * Scans: Features/{Feature}/{SubFeature}/Helpers
     * Example: Auth/Login/Helpers, Auth/Shared/Helpers
     *
     * @param string $featuresPath Path to the Features directory
     * @return int Number of helpers loaded
     */
    private static function scanNestedFeatureHelpers(string $featuresPath): int {
        $features = @scandir($featuresPath);

        if ($features === false) {
            logger('app')->warning('Failed to scan Features directory', [
                'path' => $featuresPath,
                'error' => error_get_last()['message'] ?? 'Unknown error'
            ]);
            return 0;
        }

        $discoveredCount = 0;

        foreach ($features as $feature) {
            // Skip dot directories
            if ($feature === '.' || $feature === '..') {
                continue;
            }

            $featurePath = $featuresPath . '/' . $feature;

            // Skip if not a directory
            if (!is_dir($featurePath)) {
                continue;
            }

            // Recursively scan subdirectories for Helpers folders
            $discoveredCount += self::scanSubFeaturesForHelpers($featurePath, $feature);
        }

        return $discoveredCount;
    }

    /**
     * Scan sub-features within a feature directory for Helpers folders
     *
     * @param string $featurePath Path to the feature directory
     * @param string $featureName Name of the parent feature
     * @return int Number of helpers loaded
     */
    private static function scanSubFeaturesForHelpers(string $featurePath, string $featureName): int {
        $subFeatures = @scandir($featurePath);

        if ($subFeatures === false) {
            return 0;
        }

        $discoveredCount = 0;

        foreach ($subFeatures as $subFeature) {
            // Skip dot directories
            if ($subFeature === '.' || $subFeature === '..') {
                continue;
            }

            $subFeaturePath = $featurePath . '/' . $subFeature;

            // Skip if not a directory
            if (!is_dir($subFeaturePath)) {
                continue;
            }

            // Check if this is a Helpers directory
            if ($subFeature === 'Helpers') {
                // Load helpers directly from Feature/Helpers
                $trackingPrefix = $featureName;
                $discoveredCount += self::loadHelpersFromDirectory($subFeaturePath, $trackingPrefix);
            } else {
                // Check if sub-feature contains a Helpers directory
                $helpersPath = $subFeaturePath . '/Helpers';
                if (is_dir($helpersPath)) {
                    // Track as Feature:SubFeature
                    $trackingPrefix = $featureName . ':' . $subFeature;
                    $discoveredCount += self::loadHelpersFromDirectory($helpersPath, $trackingPrefix);
                }
            }
        }

        return $discoveredCount;
    }

    /**
     * Load all helper files from a specific directory
     *
     * @param string $helpersPath Path to the helpers directory
     * @param string $trackingPrefix Prefix for tracking (e.g., "Feature" or "Feature:SubFeature")
     * @return int Number of helpers loaded
     */
    private static function loadHelpersFromDirectory(string $helpersPath, string $trackingPrefix): int {
        $helperFiles = glob($helpersPath . '/*.php');

        if ($helperFiles === false) {
            logger('app')->warning('Failed to glob helper files', [
                'tracking_prefix' => $trackingPrefix,
                'path' => $helpersPath
            ]);
            return 0;
        }

        $loadedCount = 0;

        foreach ($helperFiles as $helperFile) {
            $helperName = basename($helperFile, '.php');
            // Track as "Feature:helper" or "Feature:SubFeature:helper"
            $trackingName = $trackingPrefix . ':' . $helperName;

            // Skip if already loaded (prevent duplicates)
            if (in_array($trackingName, self::$loadedHelpers)) {
                continue;
            }

            self::loadHelperFileDirectly($helperFile, $trackingName);
            $loadedCount++;

            if (self::$debug) {
                logger('app')->debug('Loaded feature helper', [
                    'tracking_name' => $trackingName,
                    'file' => $helperFile
                ]);
            }
        }

        return $loadedCount;
    }

    /**
     * Load helper file by name
     */
    private static function loadHelperFile($helpersDir, $helperName) {
        $helperFile = $helpersDir . '/' . $helperName . '.php';

        if (file_exists($helperFile)) {
            self::loadHelperFileDirectly($helperFile, $helperName);
        }
        // Silently skip missing helpers - they may not all be present
    }

    /**
     * Load individual helper file with error handling
     */
    private static function loadHelperFileDirectly($helperFile, $helperName) {
        try {
            require_once $helperFile;
            self::$loadedHelpers[] = $helperName;
        } catch (Exception $e) {
            logger('app')->warning("Error loading helper", [
                'helper' => $helperName,
                'file' => $helperFile,
                'error' => $e->getMessage()
            ]);

            // Don't stop bootstrap for helper errors unless critical
            $criticalHelpers = ['config', 'session', 'env'];
            if (\in_array($helperName, $criticalHelpers, true)) {
                throw new Exception("Critical helper failed to load: {$helperName} - " . $e->getMessage());
            }
        }
    }

    /**
     * Initialize framework
     */
    private static function initializeFramework() {
        // Configuration handles core system initialization
        if (class_exists('Vireo\Framework\Configuration')) {
            Configuration::getInstance();
        }
    }

    /**
     * Run automatic database migrations
     */
    private static function runAutoMigrations() {
        if (!class_exists('Vireo\Framework\Database\Migration')) {
            return;
        }

        try {
            Migration::autoRun();
        } catch (Exception $e) {
            logger('database')->error("Auto-migration failed", [
                'error' => $e->getMessage()
            ]);
            // Don't stop bootstrap for migration errors
        }
    }

    /**
     * Initialize ViewEngine with framework context
     */
    private static function initializeViewEngine() {
        if (!class_exists('Vireo\Framework\View\Blade')) {
            return;
        }

        try {
            $viewEngine = Blade::getInstance();

            // Share global data using helper functions
            $sharedData = [
                'app_name' => function_exists('config') ? config('app.name', 'Vireo Framework') : 'Vireo Framework',
                'app_version' => function_exists('config') ? config('app.version', '1.0.0') : '1.0.0',
                'app_env' => function_exists('env') ? env('APP_ENV', 'production') : 'production',
                'app_url' => function_exists('config') ? config('app.url', '') : '',
                'is_local' => function_exists('env') ? (env('APP_ENV') === 'local') : false,
                'is_debug' => function_exists('env') ? (env('APP_DEBUG') === 'true') : false,
            ];

            $viewEngine->share($sharedData);
        } catch (Exception $e) {
            logger('app')->error("ViewEngine initialization failed", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Share global data with Inertia
     */
    private static function shareInertiaData() {
        if (!class_exists('Vireo\Framework\View\Inertia')) {
            return;
        }

        try {
            Inertia::share([
                'auth' => [
                    'user' => [
                        'name' => $_SESSION['user_name'] ?? null,
                        'email' => $_SESSION['user_email'] ?? null,
                        'authenticated' => $_SESSION['authenticated'] ?? false,
                    ],
                ],
                'flash' => [
                    'success' => $_SESSION['flash_success'] ?? null,
                    'error' => $_SESSION['flash_error'] ?? null,
                    'warning' => $_SESSION['flash_warning'] ?? null,
                    'info' => $_SESSION['flash_info'] ?? null,
                ],
                // Note: errors are handled by Inertia::getSessionProps() to avoid overwriting
            ]);

            // Clear flash messages after sharing (errors are cleared by Inertia::clearFlashData())
            unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_warning'], $_SESSION['flash_info']);
        } catch (Exception $e) {
            logger('app')->error("Inertia data sharing failed", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Log bootstrap status for debugging
     */
    private static function logBootstrapStatus() {
        if (app_debug()) {
            logger('app')->debug("Bootstrap completed successfully", [
                'loaded_helpers' => self::$loadedHelpers,
                'helper_count' => count(self::$loadedHelpers)
            ]);
        }
    }

    /**
     * Handle bootstrap errors gracefully
     */
    private static function handleBootstrapError($e) {
        $errorMessage = "Vireo Framework Bootstrap Error: " . $e->getMessage();

        $debug = (function_exists('env') && env('APP_DEBUG') === 'true');

        if ($debug) {
            // Show detailed error in debug mode
            echo "<div style='background:#f8d7da;color:#721c24;padding:20px;margin:10px;border-radius:5px;'>";
            echo "<h2>Vireo Framework Bootstrap Error</h2>";
            echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p><strong>File:</strong> " . $e->getFile() . ":" . $e->getLine() . "</p>";
            echo "<p><strong>Loaded Helpers:</strong> " . implode(', ', self::$loadedHelpers) . "</p>";
            echo "<p><strong>Configuration Status:</strong> " . (class_exists('Vireo\Framework\Configuration') ? 'Available' : 'Not Available') . "</p>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
            echo "</div>";
        } else {
            // Log error and show user-friendly message
            logger('app')->critical("Bootstrap failed", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'loaded_helpers' => self::$loadedHelpers
            ]);
            echo "<h1>System Initialization Error</h1>";
            echo "<p>The application could not start properly. Please contact support.</p>";
        }

        exit(1);
    }

    /**
     * Get comprehensive bootstrap status
     */
    public static function getStatus() {
        return [
            'loaded' => \defined('VIREO_FRAMEWORK_LOADED'),
            'helpers_loaded' => self::$loadedHelpers,
            'counts' => [
                'helpers' => \count(self::$loadedHelpers),
            ],
            'configuration_ready' => class_exists('Vireo\Framework\Configuration'),
            'critical_helpers_available' => [
                'config' => function_exists('config'),
                'session' => function_exists('session'),
                'env' => function_exists('env'),
            ],
            'environment' => function_exists('env') ? env('APP_ENV', 'unknown') : 'unknown',
            'debug_mode' => function_exists('env') ? (env('APP_DEBUG') === 'true') : false
        ];
    }

    /**
     * Check if specific helper is loaded
     */
    public static function hasHelper($helperName) {
        return \in_array($helperName, self::$loadedHelpers, true);
    }

    /**
     * Get loaded helpers list
     */
    public static function getLoadedHelpers() {
        return self::$loadedHelpers;
    }
}

// ============== GLOBAL HELPER FUNCTIONS FOR BOOTSTRAP ==============

if (!function_exists('framework_ready')) {
    /**
     * Check if Vireo framework is ready
     */
    function framework_ready() {
        return \defined('VIREO_FRAMEWORK_LOADED');
    }
}

if (!function_exists('framework_status')) {
    /**
     * Get comprehensive bootstrap status
     */
    function framework_status() {
        return \Vireo\Framework\Bootstrap::getStatus();
    }
}

if (!function_exists('framework_has_helper')) {
    /**
     * Check if specific helper is loaded
     */
    function framework_has_helper($helperName) {
        return \Vireo\Framework\Bootstrap::hasHelper($helperName);
    }
}
