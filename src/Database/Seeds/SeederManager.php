<?php

namespace Vireo\Framework\Database\Seeds;

use Exception;

/**
 * Seeder Manager
 *
 * Manages running database seeders
 */
class SeederManager
{
    /**
     * Seeder directory
     */
    private string $seedersPath;

    /**
     * Track which seeders have run
     */
    private array $ranSeeders = [];

    public function __construct()
    {
        $this->seedersPath = ROOT_PATH . '/Database/Seeds';
    }

    /**
     * Run a specific seeder
     */
    public function run(string $seederClass): array
    {
        try {
            // Check if class exists
            if (!class_exists($seederClass)) {
                // Try loading from file
                $this->loadSeederClass($seederClass);
            }

            if (!class_exists($seederClass)) {
                return [
                    'success' => false,
                    'error' => "Seeder class not found: {$seederClass}"
                ];
            }

            // Create instance
            $seeder = new $seederClass();

            if (!($seeder instanceof Seeder)) {
                return [
                    'success' => false,
                    'error' => "Seeder must extend Framework\\Database\\Seeds\\Seeder"
                ];
            }

            // Run seeder
            $seeder->run();

            $this->ranSeeders[] = $seederClass;

            return [
                'success' => true,
                'seeder' => $seederClass,
                'message' => "Seeded: {$seederClass}"
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'seeder' => $seederClass,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Run all seeders in a directory
     */
    public function runAll(): array
    {
        $seeders = $this->getAllSeeders();

        if (empty($seeders)) {
            return [
                'success' => true,
                'seeded' => [],
                'message' => 'No seeders found'
            ];
        }

        $seeded = [];
        $errors = [];

        foreach ($seeders as $seeder) {
            $result = $this->run($seeder);

            if ($result['success']) {
                $seeded[] = $seeder;
            } else {
                $errors[] = $result;
            }
        }

        return [
            'success' => empty($errors),
            'seeded' => $seeded,
            'errors' => $errors,
            'message' => count($seeded) . ' seeders executed'
        ];
    }

    /**
     * Load seeder class from file
     */
    private function loadSeederClass(string $className): void
    {
        // Try to find the seeder file
        $fileName = $className . '.php';
        $filePath = $this->seedersPath . '/' . $fileName;

        if (file_exists($filePath)) {
            require_once $filePath;
        }
    }

    /**
     * Get all seeder classes
     */
    private function getAllSeeders(): array
    {
        if (!is_dir($this->seedersPath)) {
            return [];
        }

        $files = scandir($this->seedersPath);
        $seeders = [];

        foreach ($files as $file) {
            if (preg_match('/^(.+)Seeder\.php$/', $file, $matches)) {
                $seeders[] = $matches[1] . 'Seeder';
            }
        }

        // Sort alphabetically, but DatabaseSeeder should run last
        usort($seeders, function ($a, $b) {
            if ($a === 'DatabaseSeeder') {
                return 1;
            }
            if ($b === 'DatabaseSeeder') {
                return -1;
            }
            return strcmp($a, $b);
        });

        return $seeders;
    }

    /**
     * Get seeders path
     */
    public function getSeedersPath(): string
    {
        return $this->seedersPath;
    }

    /**
     * Get list of seeders that have run
     */
    public function getRanSeeders(): array
    {
        return $this->ranSeeders;
    }
}
