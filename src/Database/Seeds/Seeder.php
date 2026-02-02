<?php

namespace Vireo\Framework\Database\Seeds;

use Vireo\Framework\Database\DB;
use Vireo\Framework\Database\ORM\QueryBuilder;
use PDO;

/**
 * Seeder Base Class
 *
 * Base class for all database seeders
 */
abstract class Seeder
{
    /**
     * PDO connection
     */
    protected PDO $db;

    /**
     * Connection name
     */
    protected ?string $connection = null;

    public function __construct()
    {
        $this->db = DB::connection($this->connection);
    }

    /**
     * Run the database seeds
     */
    abstract public function run(): void;

    /**
     * Call another seeder
     */
    protected function call(string $seederClass): void
    {
        // Try to load the seeder file if class doesn't exist
        if (!class_exists($seederClass, false)) {
            $this->loadSeederFile($seederClass);
        }

        if (!class_exists($seederClass)) {
            throw new \Exception("Seeder class not found: {$seederClass}");
        }

        $seeder = new $seederClass();

        if (!($seeder instanceof Seeder)) {
            throw new \Exception("Seeder must extend Vireo\\Framework\\Database\\Seeds\\Seeder");
        }

        $this->info("Seeding: {$seederClass}");
        $seeder->run();
    }

    /**
     * Load seeder file from Infrastructure/Persistence/Seeds directory
     */
    private function loadSeederFile(string $seederClass): void
    {
        // Get just the class name without namespace
        $className = basename(str_replace('\\', '/', $seederClass));

        // Try multiple seeder paths
        $possiblePaths = [
            ROOT_PATH . '/Infrastructure/Persistence/Seeds/' . $className . '.php',
            ROOT_PATH . '/Database/Seeds/' . $className . '.php',
        ];

        foreach ($possiblePaths as $filePath) {
            if (file_exists($filePath)) {
                require_once $filePath;
                return;
            }
        }
    }

    /**
     * Get a query builder instance for a table
     */
    protected function table(string $table): QueryBuilder
    {
        return (new QueryBuilder($this->connection))->table($table);
    }

    /**
     * Insert data into a table
     */
    protected function insert(string $table, array $data): void
    {
        $this->table($table)->insert($data);
    }

    /**
     * Truncate a table
     */
    protected function truncate(string $table): void
    {
        $this->table($table)->truncate();
    }

    /**
     * Execute raw SQL
     */
    protected function sql(string $sql): void
    {
        $this->db->exec($sql);
    }

    /**
     * Output info message
     */
    protected function info(string $message): void
    {
        if (app_debug()) {
            error_log($message);
        }

        // If running in CLI, also output to console
        if (PHP_SAPI === 'cli') {
            echo $message . PHP_EOL;
        }
    }

    /**
     * Log a message (alias for info)
     */
    protected function log(string $message): void
    {
        $this->info($message);
    }
}
