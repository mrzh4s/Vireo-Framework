<?php

namespace Framework\Cli\Commands;

use Framework\Cli\Command;

/**
 * MakeFeatureCommand - Generate a new feature slice
 *
 * Creates a new feature directory with the standard VSA structure.
 */
class MakeFeatureCommand extends Command
{
    /**
     * Command signature
     */
    protected string $signature = 'make:feature {name}';

    /**
     * Command description
     */
    protected string $description = 'Create a new feature slice';

    /**
     * Execute the command
     *
     * @return int Exit code
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $featurePath = ROOT_PATH . "/Features/{$name}";

        // Check if feature already exists
        if (is_dir($featurePath)) {
            $this->error("Feature already exists: {$name}");
            return self::FAILURE;
        }

        // Create feature directories
        $directories = [
            $featurePath,
            "{$featurePath}/Commands",
            "{$featurePath}/Middleware",
            "{$featurePath}/Shared",
            "{$featurePath}/Shared/Domain",
            "{$featurePath}/Shared/Ports",
            "{$featurePath}/Shared/Adapters",
            "{$featurePath}/Shared/Routes",
            "{$featurePath}/Shared/Exceptions",
        ];

        $this->info("Creating feature: {$name}");
        $this->newLine();

        $progress = $this->progressBar(count($directories));
        $progress->start();

        foreach ($directories as $dir) {
            if (!mkdir($dir, 0755, true)) {
                $this->newLine();
                $this->error("Failed to create directory: {$dir}");
                return self::FAILURE;
            }
            $progress->advance();
        }

        $progress->finish();
        $this->newLine();

        // Create a basic routes file
        $routesFile = "{$featurePath}/Shared/Routes/{$name}.php";
        $routesContent = <<<PHP
<?php

/**
 * {$name} Feature Routes
 */

use Framework\Http\Router;

// Define your routes here
// Router::get('/{$name}', YourController::class);

PHP;

        file_put_contents($routesFile, $routesContent);

        $this->success("Feature '{$name}' created successfully!");
        $this->info("Location: Features/{$name}");

        return self::SUCCESS;
    }
}
