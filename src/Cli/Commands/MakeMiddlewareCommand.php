<?php

namespace Framework\Cli\Commands;

use Framework\Cli\Command;

/**
 * MakeMiddlewareCommand - Generate a new middleware class
 *
 * Creates a new middleware class file.
 */
class MakeMiddlewareCommand extends Command
{
    /**
     * Command signature
     */
    protected string $signature = 'make:middleware {name} {--feature=}';

    /**
     * Command description
     */
    protected string $description = 'Create a new middleware class';

    /**
     * Execute the command
     *
     * @return int Exit code
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $feature = $this->option('feature');

        // Ensure name ends with 'Middleware'
        if (!str_ends_with($name, 'Middleware')) {
            $name .= 'Middleware';
        }

        // Determine path and namespace
        if ($feature) {
            $directory = ROOT_PATH . "/Features/{$feature}/Middleware";
            $namespace = "Features\\{$feature}\\Middleware";
            $relativePath = "Features/{$feature}/Middleware/{$name}.php";
        } else {
            $directory = ROOT_PATH . "/Infrastructure/Http/Middleware";
            $namespace = "Infrastructure\\Http\\Middleware";
            $relativePath = "Infrastructure/Http/Middleware/{$name}.php";
        }

        $filePath = $directory . '/' . $name . '.php';

        // Check if file already exists
        if (file_exists($filePath)) {
            $this->error("Middleware already exists: {$relativePath}");
            return self::FAILURE;
        }

        // Create directory if it doesn't exist
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                $this->error("Failed to create directory: {$directory}");
                return self::FAILURE;
            }
        }

        // Generate middleware class content
        $content = $this->generateMiddlewareClass($name, $namespace);

        // Write file
        if (file_put_contents($filePath, $content) === false) {
            $this->error("Failed to create middleware file: {$relativePath}");
            return self::FAILURE;
        }

        $this->success("Middleware created successfully!");
        $this->info("Location: {$relativePath}");

        return self::SUCCESS;
    }

    /**
     * Generate middleware class content
     *
     * @param string $className Class name
     * @param string $namespace Namespace
     * @return string Class content
     */
    private function generateMiddlewareClass(string $className, string $namespace): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use Framework\Http\Request;
use Framework\Http\Response;

/**
 * {$className} - Middleware description
 *
 * TODO: Add detailed middleware description
 */
class {$className}
{
    /**
     * Handle the request
     *
     * @param Request \$request
     * @param callable \$next
     * @return Response
     */
    public function handle(Request \$request, callable \$next): Response
    {
        // Before request processing
        // TODO: Add your logic here

        \$response = \$next(\$request);

        // After request processing
        // TODO: Add your logic here

        return \$response;
    }
}

PHP;
    }
}
