<?php

/**
 * Enhanced Router System with Smart Page Detection
 * File: core/Router.php
 * 
 * Features:
 * - Named routes with route() helper
 * - Smart page auto-detection
 * - JSON and form data handling
 * - File upload support
 * - No prefix needed
 * - Auto-finds pages in nested folders
 * - Performance-optimized with caching
 */
namespace Vireo\Framework\Http;
use Exception;

class Router
{
    private static $instance = null;
    private static $routesDiscovered = false;

    private $routes = [];
    private $namedRoutes = [];
    private $middleware = [];
    private $bindings = [];  // Interface-to-implementation bindings
    private $singletons = []; // Singleton instances
    private $factories = [];  // Factory functions for complex dependencies
    private $baseTemplatesPath = 'Infrastructure/Http/View/';
    private $basePagesPath = 'Infrastructure/Http/View/Pages/';

    // Store parsed request data
    private $requestData = [];
    private $requestFiles = [];
    private $requestHeaders = [];
    private $contentType = '';

    // Performance cache
    private $pageCache = [];

    // Parameter patterns for validation
    private $patterns = [
        'id' => '[A-Z0-9]{8}',
        'uuid' => '[a-zA-Z0-9-]{36}',
        'string' => '[a-zA-Z]+',
        'alpha' => '[a-zA-Z]+',
        'alphanum' => '[a-zA-Z0-9]+',
        'slug' => '[a-zA-Z0-9-_]+',
        'number' => '[0-9]+',
        'year' => '[0-9]{4}',
        'month' => '[0-9]{1,2}',
        'day' => '[0-9]{1,2}',
        'code' => '[A-Z0-9]{6}',
        'token' => '[a-zA-Z0-9]{16}',
        'phone' => '[0-9-+]+',
        'any' => '.*'
    ];

    /**
     * Constructor - Initialize request parsing
     */
    private function __construct()
    {
        $this->parseRequest();
        $this->discoverMiddleware();
        $this->registerDefaultBindings();
    }

    /**
     * Register default interface-to-implementation bindings
     * Auto-discovers implementations for interfaces
     */
    private function registerDefaultBindings()
    {
        // Register infrastructure services (PDO, etc.)
        $this->registerInfrastructureServices();

        // Auto-discover repository implementations
        $this->autoBindRepositories();
    }

    /**
     * Register infrastructure services like PDO, Database, etc.
     */
    private function registerInfrastructureServices()
    {
        // Register PDO factory - uses the db() helper to get connection
        $this->factory('PDO', function() {
            // Use the db() helper which returns a PDO instance
            if (function_exists('db')) {
                return db();
            }
            throw new Exception("PDO factory failed: db() helper not available");
        });
    }

    /**
     * Auto-bind repository interfaces to implementations
     */
    private function autoBindRepositories()
    {
        // Common patterns for repository bindings
        $patterns = [
            'Features\Auth\Shared\Ports\UserRepositoryInterface' => 'Features\Auth\Shared\Adapters\PgUserRepository',
        ];

        foreach ($patterns as $interface => $implementation) {
            if (interface_exists($interface) && class_exists($implementation)) {
                $this->bind($interface, $implementation);
            }
        }
    }

    /**
     * Bind an interface to a concrete implementation
     */
    public function bind($abstract, $concrete)
    {
        $this->bindings[$abstract] = $concrete;
        return $this;
    }

    /**
     * Register a factory function for creating instances
     *
     * @param string $abstract The class/interface name
     * @param callable $factory Factory function that returns an instance
     */
    public function factory($abstract, $factory)
    {
        $this->factories[$abstract] = $factory;
        return $this;
    }

    /**
     * Bind a singleton instance
     */
    public function singleton($abstract, $concrete)
    {
        $this->bind($abstract, $concrete);
        return $this;
    }

    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get router instance (alias for getInstance)
     * Used by helper functions
     */
    public static function getRouter()
    {
        return self::getInstance();
    }

    /**
     * Auto-discover and register middleware from dedicated folders
     */
    private function discoverMiddleware()
    {
        // 1. Load global middleware from Infrastructure/Http/Middleware
        $globalMiddlewarePath = ROOT_PATH . '/Infrastructure/Http/Middleware';
        $this->loadMiddlewareFromDirectory($globalMiddlewarePath);

        // 2. Load feature-specific middleware from Features/*/Middleware
        $featuresPath = ROOT_PATH . '/Features';
        if (is_dir($featuresPath)) {
            $features = scandir($featuresPath);
            foreach ($features as $feature) {
                if ($feature === '.' || $feature === '..') {
                    continue;
                }

                $featureMiddlewarePath = $featuresPath . '/' . $feature . '/Middleware';
                $this->loadMiddlewareFromDirectory($featureMiddlewarePath);
            }
        }
    }

    /**
     * Load middleware classes from a directory
     */
    private function loadMiddlewareFromDirectory($directory)
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = glob($directory . '/*Middleware.php');

        foreach ($files as $file) {
            $className = basename($file, '.php');

            // Derive middleware name from class name
            // e.g., AuthMiddleware -> auth, GuestMiddleware -> guest
            $middlewareName = $this->deriveMiddlewareName($className);

            // Try to determine the namespace from the file
            $namespace = $this->getNamespaceFromPath($file);
            $fullClassName = $namespace . '\\' . $className;

            // Include the file to ensure the class is available
            require_once $file;

            // Register the middleware
            if (class_exists($fullClassName)) {
                $this->registerMiddlewareClass($middlewareName, $fullClassName);
            }
        }
    }

    /**
     * Derive middleware name from class name
     * AuthMiddleware -> auth
     * GuestMiddleware -> guest
     * CustomAuthMiddleware -> custom-auth
     */
    private function deriveMiddlewareName($className)
    {
        // Remove 'Middleware' suffix
        $name = preg_replace('/Middleware$/', '', $className);

        // Convert PascalCase to kebab-case
        $name = preg_replace('/(?<!^)[A-Z]/', '-$0', $name);
        $name = strtolower($name);

        return $name;
    }

    /**
     * Get namespace from file path by parsing the PHP file
     */
    private function getNamespaceFromPath($filePath)
    {
        // Read the file and extract the namespace declaration
        $contents = file_get_contents($filePath);

        if ($contents !== false && preg_match('/^\s*namespace\s+([^;]+);/m', $contents, $matches)) {
            return trim($matches[1]);
        }

        // Fallback: derive namespace from path (for files without namespace)
        $relativePath = str_replace(ROOT_PATH . '/', '', $filePath);
        $dirPath = dirname($relativePath);
        $namespace = str_replace('/', '\\', $dirPath);

        return $namespace;
    }

    /**
     * Register middleware class
     */
    private function registerMiddlewareClass($name, $className)
    {
        $this->middleware[$name] = function(...$params) use ($className) {
            $instance = new $className();

            // If middleware has a handle method, call it with parameters
            if (method_exists($instance, 'handle')) {
                return $instance->handle(...$params);
            }

            // If middleware is invokable, call it directly with parameters
            if (method_exists($instance, '__invoke')) {
                return $instance(...$params);
            }

            throw new Exception("Middleware {$className} must have a handle() method or be invokable");
        };
    }

    /**
     * Auto-discover and load route modules
     * Loads routes from Infrastructure/Http/Routes and Features/Routes directories
     */
    private function discoverRoutes()
    {
        // 1. Load core routes from Infrastructure/Http/Routes
        $coreRoutesPath = ROOT_PATH . '/Infrastructure/Http/Routes';
        $this->loadRoutesFromDirectory($coreRoutesPath);

        // 2. Load feature-specific routes from Features/*/Routes
        $featuresPath = ROOT_PATH . '/Features';
        if (is_dir($featuresPath)) {
            $features = scandir($featuresPath);
            foreach ($features as $feature) {
                if ($feature === '.' || $feature === '..') {
                    continue;
                }

                $featureRoutesPath = $featuresPath . '/' . $feature . '/Shared/Routes';
                $this->loadRoutesFromDirectory($featureRoutesPath);
            }
        }
    }

    /**
     * Load all route files from a directory
     */
    private function loadRoutesFromDirectory($directory)
    {
        if (!is_dir($directory)) {
            return;
        }

        // Get all PHP files in the directory
        $files = glob($directory . '/*.php');

        // Sort files alphabetically for consistent loading order
        sort($files);

        foreach ($files as $file) {
            $this->loadRouteFile($file);
        }
    }

    /**
     * Load a single route file
     */
    private function loadRouteFile($file)
    {
        if (!file_exists($file)) {
            return;
        }

        // Include the route file
        // The file should use Router::get(), Router::post(), etc.
        require_once $file;
    }

    /**
     * Parse incoming request data
     */
    private function parseRequest()
    {
        $this->requestHeaders = function_exists('getallheaders') ? \getallheaders() : [];
        $this->contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $this->contentType = strtolower(trim(explode(';', $this->contentType)[0]));

        $method = $this->_getMethod();

        if ($method === 'GET') {
            $this->requestData = $_GET;
        } elseif (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $this->parseRequestBody();
        }

        if (!empty($_FILES)) {
            $this->requestFiles = $_FILES;
        }
    }

    /**
     * Parse request body for POST/PUT/PATCH/DELETE
     */
    private function parseRequestBody()
    {
        $rawInput = file_get_contents('php://input');

        switch ($this->contentType) {
            case 'application/json':
                $this->parseJsonData($rawInput);
                break;

            case 'application/x-www-form-urlencoded':
                $this->parseFormData();
                break;

            case 'multipart/form-data':
                $this->parseMultipartData();
                break;

            default:
                if ($this->isJsonString($rawInput)) {
                    $this->parseJsonData($rawInput);
                } else {
                    $this->parseFormData();
                }
                break;
        }
    }

    /**
     * Parse JSON data
     */
    private function parseJsonData($rawInput)
    {
        if (!empty($rawInput)) {
            $decoded = json_decode($rawInput, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->requestData = $decoded;
            } else {
                error_log("JSON parsing error: " . json_last_error_msg());
                $this->requestData = [];
            }
        }
    }

    /**
     * Parse form data
     */
    private function parseFormData()
    {
        $this->requestData = $_POST;
    }

    /**
     * Parse multipart form data
     */
    private function parseMultipartData()
    {
        $this->requestData = $_POST;
    }

    /**
     * Check if string is valid JSON
     */
    private function isJsonString($string)
    {
        if (empty($string)) return false;

        $trimmed = trim($string);
        return (
            (substr($trimmed, 0, 1) === '{' && substr($trimmed, -1) === '}') ||
            (substr($trimmed, 0, 1) === '[' && substr($trimmed, -1) === ']')
        );
    }

    /**
     * Get request data (internal method)
     */
    private function _getRequestData($key = null, $default = null)
    {
        if ($key === null) {
            return $this->requestData;
        }

        return $this->requestData[$key] ?? $default;
    }

    /**
     * Get uploaded files (internal method)
     */
    private function _getFiles($key = null)
    {
        if ($key === null) {
            return $this->requestFiles;
        }

        return $this->requestFiles[$key] ?? null;
    }

    /**
     * Get request headers (internal method)
     */
    private function _getHeaders($key = null)
    {
        if ($key === null) {
            return $this->requestHeaders;
        }

        foreach ($this->requestHeaders as $name => $value) {
            if (strtolower($name) === strtolower($key)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Get content type (internal method)
     */
    private function _getContentType()
    {
        return $this->contentType;
    }

    /**
     * Check if request is JSON (internal method)
     */
    private function _isJson()
    {
        return $this->contentType === 'application/json';
    }

    /**
     * Check if request has files (internal method)
     */
    private function _hasFiles()
    {
        return !empty($this->requestFiles);
    }

    // Note: Instance route methods removed due to PHP 8.4 restrictions
    // Use static methods instead: Router::get(), Router::post(), etc.

    /**
     * Add named route (fluent interface)
     */
    public function name($name)
    {
        if (!empty($this->routes)) {
            $lastIndex = count($this->routes) - 1;
            $this->routes[$lastIndex]['name'] = $name;
            $this->namedRoutes[$name] = $this->routes[$lastIndex];
        }
        return $this;
    }

    /**
     * Add route with method and optional name
     */
    private function addRoute($method, $pattern, $handler, $middleware = [], $name = null)
    {
        $route = [
            'method' => $method,
            'pattern' => $this->convertPattern($pattern),
            'original_pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $middleware,
            'parameters' => $this->extractParameters($pattern),
            'name' => $name
        ];

        $this->routes[] = $route;

        if ($name) {
            $this->namedRoutes[$name] = $route;
        }

        return $this;
    }

    /**
     * Convert route pattern to regex
     */
    private function convertPattern($pattern)
    {
        $pattern = preg_replace_callback('/\{([a-zA-Z0-9_]+):([a-zA-Z0-9_]+)\}/', function ($matches) {
            $type = $matches[2];

            if (isset($this->patterns[$type])) {
                return '(' . $this->patterns[$type] . ')';
            }

            if (is_numeric($type)) {
                return '([a-zA-Z0-9]{' . $type . '})';
            }

            return '([a-zA-Z0-9_-]+)';
        }, $pattern);

        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([a-zA-Z0-9_-]+)', $pattern);
        $pattern = str_replace('/', '\/', $pattern);

        return '/^' . $pattern . '$/';
    }

    /**
     * Extract parameter names from pattern
     */
    private function extractParameters($pattern)
    {
        preg_match_all('/\{([a-zA-Z0-9_]+)(?::([a-zA-Z0-9_]+))?\}/', $pattern, $matches);
        return $matches[1];
    }

    /**
     * Generate URL for named route (internal method)
     */
    private function _url($name, $parameters = [])
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new Exception("Route '$name' not found");
        }

        $route = $this->namedRoutes[$name];
        $pattern = $route['original_pattern'];
        $url = $pattern;

        foreach ($parameters as $key => $value) {
            $url = preg_replace('/\{' . $key . '(?::[a-zA-Z0-9_]+)?\}/', $value, $url);
        }

        if (preg_match('/\{[^}]+\}/', $url)) {
            throw new Exception("Missing parameters for route '$name'");
        }

        return $url;
    }

    /**
     * Check if named route exists (internal method)
     */
    private function _hasRoute($name)
    {
        return isset($this->namedRoutes[$name]);
    }

    /**
     * Get all named routes (internal method)
     */
    private function _getNamedRoutes()
    {
        return $this->namedRoutes;
    }

    /**
     * Add middleware (internal method)
     */
    private function addMiddleware($name, $callback)
    {
        $this->middleware[$name] = $callback;
        return $this;
    }

    /**
     * Execute a middleware (supports parameterized middleware)
     *
     * Examples:
     * - 'auth' -> AuthMiddleware with no params
     * - 'permission:view:admin' -> PermissionMiddleware with params ['view', 'admin']
     *
     * @param string $middlewareDefinition
     * @return bool Returns false to halt request, true to continue
     */
    private function executeMiddleware($middlewareDefinition)
    {
        // Parse middleware name and parameters
        // Format: "name:param1:param2:param3"
        $parts = explode(':', $middlewareDefinition);
        $middlewareName = $parts[0];
        $middlewareParams = array_slice($parts, 1);

        // Check if middleware exists
        if (!isset($this->middleware[$middlewareName])) {
            throw new Exception("Middleware '{$middlewareName}' not found");
        }

        // Execute middleware with parameters
        $middlewareCallback = $this->middleware[$middlewareName];
        $result = call_user_func_array($middlewareCallback, $middlewareParams);

        // Return false if middleware explicitly returned false
        return $result !== false;
    }

    /**
     * Route the current request (internal method)
     */
    private function _route()
    {
        // Discover routes on first route() call
        if (!self::$routesDiscovered) {
            $this->discoverRoutes();
            self::$routesDiscovered = true;
        }

        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $requestUri = rtrim($requestUri, '/');

        if (empty($requestUri)) {
            $requestUri = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] === $requestMethod) {
                if (preg_match($route['pattern'], $requestUri, $matches)) {
                    array_shift($matches);

                    $params = [];
                    foreach ($route['parameters'] as $index => $paramName) {
                        if (isset($matches[$index])) {
                            $params[$paramName] = $matches[$index];
                        }
                    }

                    $allParams = array_merge($params, $this->requestData);

                    // Execute middleware
                    foreach ($route['middleware'] as $middlewareDefinition) {
                        if (!$this->executeMiddleware($middlewareDefinition)) {
                            return; // Middleware halted the request
                        }
                    }

                    return $this->executeHandler($route['handler'], $matches, $allParams);
                }
            }
        }

        $this->handle404();
    }

    /**
     * Execute route handler with smart auto-detection
     */
    private function executeHandler($handler, $params = [], $namedParams = [])
    {
        try {
            if (is_string($handler)) {
                
                // 1. Check for Class@method syntax (pages/)
                if (strpos($handler, '@') !== false) {
                    return $this->executeControllerOrPage($handler, $namedParams);
                }
                
                // 2. Check for view shorthand: "auth.login"
                if (strpos($handler, '.') !== false) {
                    return $this->renderViewShorthand($handler, $namedParams);
                }
            }
            
            if (is_callable($handler)) {
                $result = call_user_func_array($handler, array_merge($params, [$namedParams]));
                
                if (is_string($result)) {
                    echo $result;
                    return;
                }
                
                return $result;
            }
            
            throw new Exception("Invalid handler: " . print_r($handler, true));
            
        } catch (Exception $e) {
            $this->handleRouterError($e);
        }
    }

    /**
     * Smart controller/page executor - auto-detects location
     */
    private function executeControllerOrPage($handler, $params = [])
    {
        try {
            list($className, $method) = explode('@', $handler);

            // If already fully namespaced (contains backslash), use directly
            if (strpos($className, '\\') !== false) {
                if (class_exists($className)) {
                    return $this->executeClass($className, $method, $params);
                }
                throw new Exception("Controller not found: {$className}");
            }

            // Build list of possible namespaces to try
            $possibleNamespaces = [
                'Infrastructure\\Http\\Controllers\\',
            ];

            // Search Features directory structure for the controller
            $featureControllers = $this->findFeatureController($className);
            $possibleNamespaces = array_merge($possibleNamespaces, $featureControllers);

            // Try without namespace (fallback)
            $possibleNamespaces[] = '';

            // Try each possible namespace
            foreach ($possibleNamespaces as $namespace) {
                $fullClassName = $namespace . $className;
                if (class_exists($fullClassName)) {
                    return $this->executeClass($fullClassName, $method, $params);
                }
            }

            // Fallback: Find page file in pages/ folder (legacy support)
            $pagePath = $this->findPageFile($className);

            if ($pagePath) {
                return $this->executeFile($pagePath, $className, $method, $params);
            }

            throw new Exception("Controller not found: {$className}. Searched in Infrastructure/Http/Controllers and Features directories.");

        } catch (Exception $e) {
            $this->handleRouterError($e);
        }
    }

    /**
     * Find controller in Features directory structure
     * Recursively searches all Features subdirectories for the controller
     *
     * @param string $className The controller class name (e.g., "LoginController")
     * @return array Array of possible namespaces where the controller might exist
     */
    private function findFeatureController($className)
    {
        $possibleNamespaces = [];
        $featuresPath = ROOT_PATH . '/Features';

        if (!is_dir($featuresPath)) {
            return $possibleNamespaces;
        }

        try {
            // Search recursively for the controller file
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($featuresPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getFilename() === $className . '.php') {
                    // Get relative path from Features directory
                    $relativePath = str_replace($featuresPath . '/', '', $file->getPath());

                    // Convert path to namespace (e.g., "Auth/Login" -> "Features\Auth\Login\")
                    $namespace = 'Features\\' . str_replace('/', '\\', $relativePath) . '\\';

                    $possibleNamespaces[] = $namespace;
                }
            }
        } catch (\Exception $e) {
            // If directory iteration fails, log and continue
            error_log("Error searching Features directory: " . $e->getMessage());
        }

        return $possibleNamespaces;
    }

    /**
     * Execute a class method directly (namespace-aware)
     * Supports automatic dependency injection for constructor parameters
     */
    private function executeClass($className, $method, $params = [])
    {
        if (!class_exists($className)) {
            throw new Exception("Class not found: {$className}");
        }

        // Instantiate the class with dependency injection
        $instance = $this->resolveClass($className);

        if (!method_exists($instance, $method)) {
            throw new Exception("Method {$method} not found in {$className}");
        }

        // Call method
        $result = call_user_func_array([$instance, $method], [$params]);

        // Handle result
        if (is_string($result)) {
            echo $result;
            return;
        }

        // Handle array results (for API responses)
        if (is_array($result)) {
            // Check if this is an API request
            if ($this->_isApiRequest()) {
                header('Content-Type: application/json');
                echo json_encode($result, JSON_PRETTY_PRINT);
                return;
            }
            // For non-API requests, just return the array
            return $result;
        }

        return $result;
    }

    /**
     * Resolve class dependencies and instantiate
     * Uses reflection to auto-resolve constructor parameters
     */
    private function resolveClass($className)
    {
        // Check if there's a factory for this class
        if (isset($this->factories[$className])) {
            return call_user_func($this->factories[$className]);
        }

        // Check if there's a binding for this class/interface
        if (isset($this->bindings[$className])) {
            $className = $this->bindings[$className];
        }

        // Check if already instantiated as singleton
        if (isset($this->singletons[$className])) {
            return $this->singletons[$className];
        }

        $reflector = new \ReflectionClass($className);

        // Cannot instantiate interfaces or abstract classes without binding
        if ($reflector->isInterface() || $reflector->isAbstract()) {
            throw new Exception(
                "Cannot instantiate {$className}. " .
                "Please register a binding using Router::bind('{$className}', ConcreteClass::class)"
            );
        }

        // If no constructor, just instantiate
        if (!$reflector->getConstructor()) {
            return new $className();
        }

        $constructor = $reflector->getConstructor();
        $parameters = $constructor->getParameters();

        // If constructor has no parameters, just instantiate
        if (empty($parameters)) {
            return new $className();
        }

        // Resolve constructor dependencies
        $dependencies = [];
        foreach ($parameters as $parameter) {
            $dependency = $parameter->getType();

            // Skip if no type hint
            if (!$dependency) {
                // Check if parameter has default value
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new Exception(
                        "Cannot resolve parameter \${$parameter->getName()} in {$className}. " .
                        "Parameter must have a type hint or default value."
                    );
                }
                continue;
            }

            // Handle ReflectionNamedType (PHP 7.1+)
            if ($dependency instanceof \ReflectionNamedType) {
                // Skip built-in types (string, int, bool, etc.)
                if ($dependency->isBuiltin()) {
                    if ($parameter->isDefaultValueAvailable()) {
                        $dependencies[] = $parameter->getDefaultValue();
                    } else {
                        throw new Exception(
                            "Cannot resolve built-in type parameter \${$parameter->getName()} in {$className}. " .
                            "Parameter must have a default value."
                        );
                    }
                    continue;
                }

                $dependencyClassName = $dependency->getName();
            } else {
                // Fallback for older PHP versions
                $dependencyClassName = (string) $dependency;
            }

            // Recursively resolve the dependency
            try {
                $dependencies[] = $this->resolveClass($dependencyClassName);
            } catch (\Exception $e) {
                throw new Exception(
                    "Cannot resolve dependency {$dependencyClassName} for {$className}: " . $e->getMessage()
                );
            }
        }

        // Instantiate with resolved dependencies
        $instance = $reflector->newInstanceArgs($dependencies);

        return $instance;
    }

    /**
     * Find page file - supports nested structure with caching
     */
    private function findPageFile($pageName)
    {
        // Check cache first
        if (isset($this->pageCache[$pageName])) {
            return $this->pageCache[$pageName];
        }
        
        $possiblePaths = [];
        
        // If contains slash, use direct path
        if (strpos($pageName, '/') !== false) {
            $possiblePaths[] = ROOT_PATH . $this->basePagesPath . $pageName . '.php';
        } else {
            // Common locations (fast check first)
            $possiblePaths[] = ROOT_PATH . $this->basePagesPath . $pageName . '.php';
            $possiblePaths[] = ROOT_PATH . $this->basePagesPath . 'auth/' . $pageName . '.php';
            $possiblePaths[] = ROOT_PATH . $this->basePagesPath . 'home/' . $pageName . '.php';
            $possiblePaths[] = ROOT_PATH . $this->basePagesPath . 'applications/' . $pageName . '.php';
            $possiblePaths[] = ROOT_PATH . $this->basePagesPath . 'profile/' . $pageName . '.php';
            $possiblePaths[] = ROOT_PATH . $this->basePagesPath . 'settings/' . $pageName . '.php';
        }
        
        // Check common paths first
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $this->pageCache[$pageName] = $path;
                return $path;
            }
        }
        
        // If not found in common paths, search recursively
        $foundPath = $this->searchPageRecursively($pageName);
        
        if ($foundPath) {
            $this->pageCache[$pageName] = $foundPath;
            return $foundPath;
        }
        
        return null;
    }

    /**
     * Recursively search for page file
     */
    private function searchPageRecursively($pageName, $dir = null)
    {
        if ($dir === null) {
            $dir = ROOT_PATH . $this->basePagesPath;
        }
        
        if (!is_dir($dir)) {
            return null;
        }
        
        $files = scandir($dir);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === 'BasePage.php') {
                continue;
            }
            
            $filePath = $dir . '/' . $file;
            
            // If it's the file we're looking for
            if (is_file($filePath) && $file === $pageName . '.php') {
                return $filePath;
            }
            
            // If it's a directory, search recursively
            if (is_dir($filePath)) {
                $result = $this->searchPageRecursively($pageName, $filePath);
                if ($result) {
                    return $result;
                }
            }
        }
        
        return null;
    }

    /**
     * Execute file (controller or page)
     * Supports automatic dependency injection for constructor parameters
     */
    private function executeFile($filePath, $className, $method, $params)
    {
        require_once $filePath;

        // Extract actual class name (remove path if present)
        $parts = explode('/', $className);
        $actualClassName = end($parts);

        if (!class_exists($actualClassName)) {
            throw new Exception("Class not found: {$actualClassName} in {$filePath}");
        }

        // Instantiate with dependency injection
        $instance = $this->resolveClass($actualClassName);

        if (!method_exists($instance, $method)) {
            throw new Exception("Method {$method} not found in {$actualClassName}");
        }

        // Call method
        $result = call_user_func_array([$instance, $method], [$params]);

        // Handle result
        if (is_string($result)) {
            echo $result;
            return;
        }

        return $result;
    }

    /**
     * Render view using dot notation
     */
    private function renderViewShorthand($viewFile, $params = [])
    {
        try {
            if (function_exists('view')) {
                $content = view($viewFile, $params);
                echo $content;
                return;
            }

            return $this->renderView($viewFile, $params);
        } catch (Exception $e) {
            $this->handleViewError($e, $viewFile, $params);
        }
    }

    /**
     * Render view file
     */
    private function renderView($viewFile, $params = [])
    {
        try {
            $viewPath = str_replace('.', '/', $viewFile);
            $fullPath = ROOT_PATH . $this->baseTemplatesPath . $viewPath . '.php';

            if (!file_exists($fullPath)) {
                throw new Exception("View file not found: {$fullPath}");
            }

            foreach ($params as $key => $value) {
                $_GET[$key] = $value;
            }

            extract($params);

            include $fullPath;
        } catch (Exception $e) {
            $this->handleViewError($e, $viewFile, $params);
        }
    }

    /**
     * Handle router errors
     */
    private function handleRouterError($e)
    {
        if (app('debug') === true) {
            echo "<h1>Router Error</h1>";
            echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p><strong>File:</strong> " . $e->getFile() . ":" . $e->getLine() . "</p>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        } else {
            error_log("Router Error: " . $e->getMessage());
            $this->handle500();
        }
    }

    /**
     * Handle view errors
     */
    private function handleViewError($e, $viewFile, $params = [])
    {
        if (app('debug') === true) {
            echo "<div style='background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;margin:10px;border-radius:4px;'>";
            echo "<h3>Router View Error</h3>";
            echo "<p><strong>View:</strong> " . htmlspecialchars($viewFile) . "</p>";
            echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p><strong>File:</strong> " . $e->getFile() . ":" . $e->getLine() . "</p>";

            if (!empty($params)) {
                echo "<p><strong>Parameters:</strong></p>";
                echo "<pre style='background:#fff;padding:10px;border-radius:3px;'>" . print_r($params, true) . "</pre>";
            }

            echo "<p><strong>Stack Trace:</strong></p>";
            echo "<pre style='background:#fff;padding:10px;border-radius:3px;font-size:12px;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
            echo "</div>";
        } else {
            error_log("Router View Error ({$viewFile}): " . $e->getMessage());
            $this->handle404();
        }
    }

    /**
     * Handle 404 errors
     */
    private function handle404()
    {
        http_response_code(404);

        if ($this->_isApiRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'Client Error',
                'message' => 'Endpoint not found',
                'timestamp' => date('Y-m-d H:i:s'),
                'server_time' => time()
            ], JSON_PRETTY_PRINT);
            return;
        }

        $error404Path = ROOT_PATH . $this->baseTemplatesPath . 'error/404.php';
        if (file_exists($error404Path)) {
            include $error404Path;
            return;
        }

        echo "<!DOCTYPE html>
            <html>
            <head>
                <title>404 - Page Not Found</title>
                <meta charset='utf-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1'>
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                    h1 { color: #dc3545; }
                    p { color: #6c757d; }
                    a { color: #007bff; text-decoration: none; }
                </style>
            </head>
            <body>
                <h1>404 - Page Not Found</h1>
                <p>The requested page could not be found.</p>
                <a href='/'>Return to Home</a>
            </body>
            </html>";
    }

    /**
     * Handle 500 errors
     */
    private function handle500()
    {
        http_response_code(500);

        if ($this->_isApiRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'Server Error',
                'message' => 'Internal server error',
                'timestamp' => date('Y-m-d H:i:s'),
                'server_time' => time()
            ], JSON_PRETTY_PRINT);
            return;
        }

        $error500Path = ROOT_PATH . $this->baseTemplatesPath . 'error/500.php';
        if (file_exists($error500Path)) {
            include $error500Path;
            return;
        }

        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>500 - Internal Server Error</title>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1'>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                h1 { color: #dc3545; }
                p { color: #6c757d; }
                a { color: #007bff; text-decoration: none; }
            </style>
        </head>
        <body>
            <h1>500 - Internal Server Error</h1>
            <p>Something went wrong. Please try again later.</p>
            <a href='/'>Return to Home</a>
        </body>
        </html>";
    }

    /**
     * Check if current request is API (internal method)
     */
    private function _isApiRequest()
    {
        return strpos($_SERVER['REQUEST_URI'], '/api/') === 0;
    }

    /**
     * Add parameter validation pattern (internal method)
     */
    private function _addPattern($name, $pattern)
    {
        $this->patterns[$name] = $pattern;
        return $this;
    }

    /**
     * Get all registered patterns (internal method)
     */
    private function _getPatterns()
    {
        return $this->patterns;
    }

    /**
     * Get request method (internal method)
     */
    private function _getMethod()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Static wrapper methods for fluent interface
     */

    public static function get($pattern, $handler, $middleware = [], $name = null)
    {
        $instance = self::getInstance();
        $instance->addRoute('GET', $pattern, $handler, $middleware, $name);
        return $instance;
    }

    public static function post($pattern, $handler, $middleware = [], $name = null)
    {
        $instance = self::getInstance();
        $instance->addRoute('POST', $pattern, $handler, $middleware, $name);
        return $instance;
    }

    public static function put($pattern, $handler, $middleware = [], $name = null)
    {
        $instance = self::getInstance();
        $instance->addRoute('PUT', $pattern, $handler, $middleware, $name);
        return $instance;
    }

    public static function delete($pattern, $handler, $middleware = [], $name = null)
    {
        $instance = self::getInstance();
        $instance->addRoute('DELETE', $pattern, $handler, $middleware, $name);
        return $instance;
    }

    public static function match($methods, $pattern, $handler, $middleware = [], $name = null)
    {
        $instance = self::getInstance();
        foreach ($methods as $method) {
            $instance->addRoute(strtoupper($method), $pattern, $handler, $middleware, $name);
        }
        return $instance;
    }

    public static function middleware($name, $callback)
    {
        return self::getInstance()->addMiddleware($name, $callback);
    }

    public static function route()
    {
        return self::getInstance()->_route();
    }

    public static function url($name, $parameters = [])
    {
        return self::getInstance()->_url($name, $parameters);
    }

    public static function hasRoute($name)
    {
        return self::getInstance()->_hasRoute($name);
    }

    public static function getNamedRoutes()
    {
        return self::getInstance()->_getNamedRoutes();
    }

    public static function addPattern($name, $pattern)
    {
        return self::getInstance()->_addPattern($name, $pattern);
    }

    public static function getPatterns()
    {
        return self::getInstance()->_getPatterns();
    }

    public static function getMethod()
    {
        return self::getInstance()->_getMethod();
    }

    public static function isApiRequest()
    {
        return self::getInstance()->_isApiRequest();
    }

    public static function getRequestData($key = null, $default = null)
    {
        return self::getInstance()->_getRequestData($key, $default);
    }

    public static function getFiles($key = null)
    {
        return self::getInstance()->_getFiles($key);
    }

    public static function getHeaders($key = null)
    {
        return self::getInstance()->_getHeaders($key);
    }

    public static function getContentType()
    {
        return self::getInstance()->_getContentType();
    }

    public static function isJson()
    {
        return self::getInstance()->_isJson();
    }

    public static function hasFiles()
    {
        return self::getInstance()->_hasFiles();
    }

    /**
     * Register interface-to-implementation binding (static)
     *
     * Usage:
     * Router::registerBinding(UserRepositoryInterface::class, PgUserRepository::class);
     */
    public static function registerBinding($abstract, $concrete)
    {
        return self::getInstance()->bind($abstract, $concrete);
    }

    /**
     * Register a factory function (static)
     *
     * Usage:
     * Router::registerFactory(PDO::class, function() {
     *     return new PDO($dsn, $user, $pass);
     * });
     */
    public static function registerFactory($abstract, $factory)
    {
        return self::getInstance()->factory($abstract, $factory);
    }

    /**
     * Register singleton binding (static)
     *
     * Usage:
     * Router::registerSingleton(CacheInterface::class, RedisCache::class);
     */
    public static function registerSingleton($abstract, $concrete)
    {
        return self::getInstance()->singleton($abstract, $concrete);
    }
}

