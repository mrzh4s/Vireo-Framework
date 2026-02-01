<?php

namespace Vireo\Framework\Cli\Commands\Make;

use Vireo\Framework\Cli\Command;

/**
 * Make Seeder Command
 *
 * Generates a new seeder file
 */
class MakeSeederCommand extends Command
{
    /**
     * Command signature
     */
    protected string $signature = 'make:seeder {name}';

    /**
     * Command description
     */
    protected string $description = 'Create a new seeder class';

    /**
     * Execute the command
     */
    public function handle(): int
    {
        $name = $this->argument('name');

        // Ensure name ends with "Seeder"
        if (!str_ends_with($name, 'Seeder')) {
            $name .= 'Seeder';
        }

        $path = ROOT_PATH . '/Database/Seeds';
        $filePath = $path . '/' . $name . '.php';

        // Create directory if it doesn't exist
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        // Check if file already exists
        if (file_exists($filePath)) {
            $this->error("Seeder already exists: {$name}");
            return self::FAILURE;
        }

        // Generate seeder content
        $content = $this->getStub($name);

        // Write file
        if (file_put_contents($filePath, $content) === false) {
            $this->error("Failed to create seeder: {$name}");
            return self::FAILURE;
        }

        $this->success("Seeder created successfully!");
        $this->newLine();
        $this->info("File: {$name}.php");
        $this->info("Path: {$filePath}");

        return self::SUCCESS;
    }

    /**
     * Get seeder stub
     */
    private function getStub(string $name): string
    {
        return <<<PHP
<?php

use Vireo\Framework\Database\Seeds\Seeder;

/**
 * {$name}
 */
class {$name} extends Seeder
{
    /**
     * Run the database seeds
     */
    public function run(): void
    {
        // Example: Insert data
        // \$this->table('users')->insert([
        //     ['name' => 'John Doe', 'email' => 'john@example.com'],
        //     ['name' => 'Jane Doe', 'email' => 'jane@example.com'],
        // ]);

        // Example: Call another seeder
        // \$this->call(AnotherSeeder::class);
    }
}
PHP;
    }
}
