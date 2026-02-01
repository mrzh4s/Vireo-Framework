<?php

namespace Vireo\Framework\Cli\Commands\Seed;

use Vireo\Framework\Cli\Command;
use Vireo\Framework\Database\Seeds\SeederManager;

/**
 * Seed Command
 *
 * Runs database seeders
 */
class SeedCommand extends Command
{
    /**
     * Command signature
     */
    protected string $signature = 'seed {--class=} {--force}';

    /**
     * Command description
     */
    protected string $description = 'Seed the database with records';

    /**
     * Execute the command
     */
    public function handle(): int
    {
        $class = $this->option('class');
        $force = $this->option('force');

        $this->info('Seeding database...');
        $this->newLine();

        // Confirm if not forced
        if (!$force) {
            if (!$this->confirm('Do you want to run database seeders?', true)) {
                $this->info('Seeding cancelled.');
                return self::SUCCESS;
            }
            $this->newLine();
        }

        try {
            $manager = new SeederManager();

            if ($class) {
                // Run specific seeder
                $result = $manager->run($class);

                if ($result['success']) {
                    $this->success("Seeded: {$class}");
                } else {
                    $this->error("Seeding failed: " . ($result['error'] ?? 'Unknown error'));
                    return self::FAILURE;
                }
            } else {
                // Run all seeders
                $result = $manager->runAll();

                if ($result['success']) {
                    if (empty($result['seeded'])) {
                        $this->info('No seeders found.');
                    } else {
                        $this->success('Database seeding completed!');
                        $this->newLine();

                        $this->table(
                            ['Seeder', 'Status'],
                            array_map(fn($s) => [$s, $this->output->getColor()->green('Seeded')], $result['seeded'])
                        );
                    }
                } else {
                    $this->error('Seeding failed!');

                    if (isset($result['errors'])) {
                        $this->newLine();
                        foreach ($result['errors'] as $error) {
                            $seeder = $error['seeder'] ?? 'Unknown';
                            $message = $error['error'] ?? 'Unknown error';
                            $this->error("  {$seeder}: {$message}");
                        }
                    }

                    return self::FAILURE;
                }
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Seeding error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
