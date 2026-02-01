<?php
namespace Framework\View;
use Exception;

class Blade {
    private static $instance = null;
    private $sections = [];
    private $stacks = [];
    private $components = [];
    private $data = [];
    private $currentLayout = null;
    private $currentSection = null;
    private $currentStack = null;
    private $viewPaths = [];
    private $componentPaths = [];
    private $discoveredComponents = [];

    public function __construct() {
        // Base view paths
        $this->viewPaths = [
            ROOT_PATH . '/Infrastructure/Http/View/',
            ROOT_PATH . '/Infrastructure/Http/View/Components/'
        ];

        // Auto-discover all component subdirectories
        $this->autoDiscoverComponentPaths();

        // Auto-discover and register component types
        $this->autoDiscoverComponents();
    }

    /**
     * AUTO-DISCOVERY: Scan Components folder and discover all subdirectories
     */
    private function autoDiscoverComponentPaths() {
        $componentsBaseDir = ROOT_PATH . '/Infrastructure/Http/View/Components/';

        if (!is_dir($componentsBaseDir)) {
            error_log("Blade: Components directory not found: {$componentsBaseDir}");
            return;
        }

        // Add base components directory first
        $this->componentPaths[] = $componentsBaseDir;

        // Recursively scan for subdirectories
        $this->scanComponentDirectories($componentsBaseDir);
    }

    /**
     * Recursively scan directory for component folders
     */
    private function scanComponentDirectories($directory, $maxDepth = 3, $currentDepth = 0) {
        if ($currentDepth >= $maxDepth) {
            return;
        }

        $items = scandir($directory);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $directory . $item;

            if (is_dir($fullPath)) {
                // Add this directory to component paths
                $this->componentPaths[] = $fullPath . '/';

                // Recursively scan subdirectories
                $this->scanComponentDirectories($fullPath . '/', $maxDepth, $currentDepth + 1);
            }
        }
    }

    /**
     * AUTO-DISCOVERY: Scan components folder and register component types
     */
    private function autoDiscoverComponents() {
        $componentsDir = ROOT_PATH . '/Infrastructure/Http/View/Components/';

        if (!is_dir($componentsDir)) {
            return;
        }

        // Scan for subdirectories in components/
        $items = scandir($componentsDir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $componentsDir . $item;

            // If it's a directory, register it as a component type
            if (is_dir($fullPath)) {
                $componentType = strtolower($item);
                $this->discoveredComponents[$componentType] = new ComponentRenderer($item);

                // Merge with components array
                $this->components[$componentType] = $this->discoveredComponents[$componentType];

                // Create dynamic global function for this component type
                $this->createComponentFunction($componentType);
            }
        }
    }

    /**
     * Dynamically create global component functions
     */
    private function createComponentFunction($componentType) {
        $functionName = $componentType;

        // Skip if function already exists
        if (function_exists($functionName)) {
            return;
        }

        // Create the function dynamically using eval (carefully controlled)
        $functionCode = "
        if (!function_exists('{$functionName}')) {
            function {$functionName}(\$component, \$data = []) {
                try {
                    return \Framework\View\Blade::getInstance()->renderComponent('{$componentType}', \$component, \$data);
                } catch (Exception \$e) {
                    return '<!-- Component Error (' . '{$componentType}' . '): ' . \$e->getMessage() . ' -->';
                }
            }
        }";

        eval($functionCode);
    }

    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();

            // Create ViewEngine alias for backward compatibility
            if (!class_exists('ViewEngine', false)) {
                class_alias('Framework\View\Blade', 'ViewEngine');
            }
        }
        return self::$instance;
    }

    /**
     * MAIN RENDER METHOD
     */
    public function render($view, $data = []) {
        try {
            // Reset state for new render
            $this->currentLayout = null;
            $this->sections = [];
            $this->stacks = [];

            // Merge data
            $renderData = array_merge($this->data, $data);

            // Resolve view path
            $viewPath = $this->resolveViewPath($view);

            if (!file_exists($viewPath)) {
                throw new Exception("View not found: {$view} at {$viewPath}");
            }

            // Start output buffering
            ob_start();

            // Extract data to variables
            extract($renderData);

            // Make ViewEngine available in views
            $view_engine = $this;
            $__view_engine = $this;

            // Include the view directly
            include $viewPath;

            $content = ob_get_clean();

            // If layout was set, render it
            if ($this->currentLayout) {
                return $this->renderLayout($this->currentLayout, $content, $renderData);
            }

            return $content;

        } catch (Exception $e) {
            return $this->handleViewError($e, $view, $data);
        }
    }

    /**
     * Render layout with content
     */
    private function renderLayout($layoutName, $content, $data) {
        try {
            $layoutPath = $this->resolveLayoutPath($layoutName);

            if (!file_exists($layoutPath)) {
                throw new Exception("Layout not found: {$layoutName} at {$layoutPath}");
            }

            // Store content as main section
            $this->sections['__main_content'] = $content;

            // Render layout
            ob_start();
            extract($data);

            $view_engine = $this;
            $__view_engine = $this;

            include $layoutPath;

            return ob_get_clean();

        } catch (Exception $e) {
            if (defined('APP_DEBUG') && env('APP_DEBUG') === 'true') {
                return $this->handleViewError($e, "Layout: " . $layoutName, $data);
            } else {
                error_log("Layout render error ({$layoutName}): " . $e->getMessage());
                return $content;
            }
        }
    }

    /**
     * Resolve layout path - FIXED FOR LAYOUTS FOLDER
     */
    private function resolveLayoutPath($layout) {
        // Convert dot notation to path
        $path = str_replace('.', '/', $layout);

        // Strip "layouts/" or "Layouts/" prefix if present (avoid duplication)
        if (stripos($path, 'layouts/') === 0) {
            $path = substr($path, 8); // Remove "layouts/" prefix
        }

        $path .= '.php';

        // Check components/Layouts/ first (standard location)
        $layoutsPath = ROOT_PATH . '/Infrastructure/Http/View/Components/Layouts/' . $path;
        if (file_exists($layoutsPath)) {
            return $layoutsPath;
        }

        // Check all component paths as fallback
        foreach ($this->componentPaths as $basePath) {
            $fullPath = rtrim($basePath, '/') . '/' . $path;
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        // Default path
        return ROOT_PATH . '/Infrastructure/Http/View/Components/Layouts/' . $path;
    }

    /**
     * Extend a layout
     */
    public function extend($layout) {
        $this->currentLayout = $layout;
    }

    /**
     * Start a section
     */
    public function section($name, $content = null) {
        if ($content !== null) {
            $this->sections[$name] = $content;
        } else {
            ob_start();
            $this->currentSection = $name;
        }
    }

    /**
     * End a section
     */
    public function endsection() {
        if (isset($this->currentSection)) {
            $this->sections[$this->currentSection] = ob_get_clean();
            unset($this->currentSection);
        }
    }

    /**
     * Yield a section
     */
    public function yield($section, $default = '') {
        // Special handling for 'content' section
        if ($section === 'content') {
            if (isset($this->sections['content']) && !empty($this->sections['content'])) {
                return $this->sections['content'];
            }
            if (isset($this->sections['__main_content'])) {
                return $this->sections['__main_content'];
            }
        }

        return $this->sections[$section] ?? $default;
    }

    /**
     * Check if section has content
     */
    public function hasSection($section) {
        return isset($this->sections[$section]) && !empty($this->sections[$section]);
    }

    /**
     * Push to a stack
     */
    public function push($stack, $content = null) {
        if ($content !== null) {
            if (!isset($this->stacks[$stack])) {
                $this->stacks[$stack] = [];
            }
            $this->stacks[$stack][] = $content;
        } else {
            ob_start();
            $this->currentStack = $stack;
        }
    }

    /**
     * End push
     */
    public function endpush() {
        if (isset($this->currentStack)) {
            if (!isset($this->stacks[$this->currentStack])) {
                $this->stacks[$this->currentStack] = [];
            }
            $this->stacks[$this->currentStack][] = ob_get_clean();
            unset($this->currentStack);
        }
    }

    /**
     * Render stack content
     */
    public function stack($name, $separator = "\n") {
        if (isset($this->stacks[$name])) {
            return implode($separator, $this->stacks[$name]);
        }
        return '';
    }

    /**
     * Include a component
     */
    public function component($component, $data = []) {
        try {
            $componentPath = $this->resolveComponentPath($component);

            if (!file_exists($componentPath)) {
                throw new Exception("Component not found: {$component} at {$componentPath}");
            }

            // Merge data
            $componentData = array_merge($this->data, $data);
            extract($componentData);

            // Component-specific data
            $slot = $data['slot'] ?? '';
            $attributes = $data['attributes'] ?? [];

            // Make ViewEngine available
            $view_engine = $this;
            $__view_engine = $this;

            ob_start();
            include $componentPath;
            return ob_get_clean();

        } catch (Exception $e) {
            if (defined('APP_DEBUG') && function_exists('env') && env('APP_DEBUG') === 'true') {
                return "<!-- Component Error: {$e->getMessage()} -->";
            }
            error_log("Component Error: {$e->getMessage()}");
            return '';
        }
    }

    /**
     * Render component with auto-discovery
     */
    public function renderComponent($type, $name, $data = []) {
        if (isset($this->components[$type])) {
            return $this->components[$type]->render($name, $data);
        }

        return $this->component("{$type}.{$name}", $data);
    }

    /**
     * Resolve view path
     */
    private function resolveViewPath($view) {
        $path = str_replace('.', '/', $view) . '.php';

        foreach ($this->viewPaths as $basePath) {
            $fullPath = rtrim($basePath, '/') . '/' . $path;
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        return rtrim($this->viewPaths[0], '/') . '/' . $path;
    }

    /**
     * Resolve component path
     */
    private function resolveComponentPath($component) {
        $path = str_replace('.', '/', $component) . '.php';

        foreach ($this->componentPaths as $basePath) {
            $fullPath = rtrim($basePath, '/') . '/' . $path;
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        return rtrim($this->componentPaths[0], '/') . '/' . $path;
    }

    /**
     * Share data with all views
     */
    public function share($key, $value = null) {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }
    }

    /**
     * Handle view rendering errors
     */
    private function handleViewError($e, $view = '', $data = []) {
        $isDebug = defined('APP_DEBUG') && function_exists('env') && env('APP_DEBUG') === 'true';

        if ($isDebug) {
            $errorOutput = "<div style='background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;margin:10px;border-radius:4px;font-family:monospace;'>";
            $errorOutput .= "<h3 style='margin:0 0 10px 0;'>ViewEngine Error</h3>";
            $errorOutput .= "<p><strong>View:</strong> " . htmlspecialchars($view) . "</p>";
            $errorOutput .= "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            $errorOutput .= "<p><strong>File:</strong> " . $e->getFile() . ":" . $e->getLine() . "</p>";

            if (!empty($data)) {
                $errorOutput .= "<details><summary><strong>View Data</strong></summary><pre style='background:#fff;padding:10px;overflow:auto;'>" . htmlspecialchars(print_r($data, true)) . "</pre></details>";
            }

            $errorOutput .= "<details><summary><strong>Debug Info</strong></summary><pre style='background:#fff;padding:10px;overflow:auto;'>" . htmlspecialchars(print_r($this->getDebugInfo(), true)) . "</pre></details>";
            $errorOutput .= "<details><summary><strong>Stack Trace</strong></summary><pre style='background:#fff;padding:10px;font-size:12px;overflow:auto;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre></details>";
            $errorOutput .= "</div>";

            return $errorOutput;
        } else {
            error_log("ViewEngine Error ({$view}): " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            return "<!-- ViewEngine Error: " . htmlspecialchars($e->getMessage()) . " -->";
        }
    }

    /**
     * Get debug information
     */
    public function getDebugInfo() {
        return [
            'view_paths' => $this->viewPaths,
            'component_paths_count' => count($this->componentPaths),
            'component_paths' => $this->componentPaths,
            'sections' => array_keys($this->sections),
            'sections_content' => $this->sections,
            'stacks' => array_keys($this->stacks),
            'shared_data_keys' => array_keys($this->data),
            'registered_components' => array_keys($this->components),
            'discovered_components' => array_keys($this->discoveredComponents),
            'current_layout' => $this->currentLayout,
        ];
    }
}

/**
 * Component Renderer Class
 */
class ComponentRenderer {
    private $basePath;

    public function __construct($basePath) {
        $this->basePath = $basePath;
    }

    public function render($component, $data = []) {
        $engine = Blade::getInstance();
        return $engine->component("{$this->basePath}.{$component}", $data);
    }
}
