<?php
/**
 * Database Migration Manager (Enhanced)
 * File: Framework/Database/Migration.php
 *
 * FEATURES:
 * - Supports both SQL files (.sql) and PHP migration classes
 * - Proper rollback support with down() methods for PHP migrations
 * - Batch tracking for precise rollbacks
 * - Transaction support for safety
 * - Multi-database connection support
 * - Auto-discovery from both directories
 * - Backward compatible with legacy SQL migrations
 */
namespace Framework\Database;

use Framework\Database\DB;
use Framework\Database\Migrations\Schema;
use Exception;
use PDO;

class Migration {
    private static $instance = null;
    private $db;
    private $sqlMigrationsPath;
    private $phpMigrationsPath;
    private $migrationsTable = 'migrations';
    private $connection;

    private function __construct(?string $connection = null) {
        $this->connection = $connection;
        $this->db = DB::connection($connection);
        $this->sqlMigrationsPath = ROOT_PATH . '/Infrastructure/Persistence/Migrations';
        $this->phpMigrationsPath = ROOT_PATH . '/Infrastructure/Persistence/Migrations';
        $this->ensureMigrationsTable();
    }

    /**
     * Get schema builder with migration's connection
     * Used by migration files to ensure they use the correct database
     *
     * @return Schema
     */
    protected function schema() {
        // Set the Schema's connection to match this migration's connection
        return Schema::connection($this->connection);
    }

    public static function getInstance(?string $connection = null) {
        if (self::$instance === null) {
            self::$instance = new self($connection);
        }
        return self::$instance;
    }

    /**
     * Create migrations tracking table if it doesn't exist
     * Supports PostgreSQL, MySQL, and SQLite
     */
    private function ensureMigrationsTable() {
        try {
            // Detect database driver
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'pgsql') {
                // PostgreSQL syntax
                $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
                    id SERIAL PRIMARY KEY,
                    migration VARCHAR(255) UNIQUE NOT NULL,
                    batch INTEGER NOT NULL,
                    type VARCHAR(10) DEFAULT 'sql',
                    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
            } elseif ($driver === 'mysql') {
                // MySQL syntax
                $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    migration VARCHAR(255) UNIQUE NOT NULL,
                    batch INT NOT NULL,
                    type VARCHAR(10) DEFAULT 'sql',
                    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
            } else {
                // SQLite syntax
                $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    migration VARCHAR(255) UNIQUE NOT NULL,
                    batch INTEGER NOT NULL,
                    type VARCHAR(10) DEFAULT 'sql',
                    executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )";
            }

            $this->db->exec($sql);
        } catch (Exception $e) {
            logger('database')->error("Failed to create migrations table", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Run all pending migrations (SQL and PHP)
     */
    public function run() {
        try {
            $pendingMigrations = $this->getPendingMigrations();

            if (empty($pendingMigrations)) {
                if (app_debug()) {
                    logger('database')->debug("No pending migrations to run");
                }
                return [
                    'success' => true,
                    'migrated' => [],
                    'message' => 'No pending migrations'
                ];
            }

            $batch = $this->getNextBatchNumber();
            $migrated = [];
            $errors = [];

            foreach ($pendingMigrations as $migration) {
                try {
                    $type = $this->getMigrationType($migration);

                    if ($type === 'php') {
                        $this->runPhpMigration($migration, $batch, 'up');
                    } else {
                        $this->runSqlMigration($migration, $batch);
                    }

                    $migrated[] = $migration;

                    if (app_debug()) {
                        logger('database')->info("Migration completed", [
                            'migration' => $migration,
                            'type' => $type,
                            'batch' => $batch
                        ]);
                    }
                } catch (Exception $e) {
                    $errors[] = [
                        'migration' => $migration,
                        'error' => $e->getMessage()
                    ];
                    logger('database')->error("Migration failed", [
                        'migration' => $migration,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return [
                'success' => empty($errors),
                'migrated' => $migrated,
                'errors' => $errors,
                'message' => count($migrated) . ' migrations executed'
            ];

        } catch (Exception $e) {
            logger('database')->error("Migration run error", [
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Run a PHP migration class
     */
    private function runPhpMigration(string $migration, int $batch, string $direction = 'up') {
        $className = $this->getMigrationClassName($migration);
        $filePath = $this->phpMigrationsPath . '/' . $migration;

        if (!file_exists($filePath)) {
            throw new Exception("PHP migration file not found: {$filePath}");
        }

        // Load migration file
        require_once $filePath;

        if (!class_exists($className)) {
            throw new Exception("Migration class not found: {$className}");
        }

        $migrationInstance = new $className();

        // IMPORTANT: Set Schema's default connection to match this migration's connection
        // This ensures Schema::create() uses the correct database
        $originalSchemaConnection = null;
        if (method_exists('Framework\Database\Migrations\Schema', 'connection')) {
            // Store original and set to migration's connection
            Schema::connection($this->connection);
        }

        // Start transaction
        $this->db->beginTransaction();

        try {
            // Execute up() or down() method
            if ($direction === 'up') {
                $migrationInstance->up();

                // Record migration
                $stmt = $this->db->prepare(
                    "INSERT INTO {$this->migrationsTable} (migration, batch, type) VALUES (?, ?, 'php')"
                );
                $stmt->execute([$migration, $batch]);
            } else {
                $migrationInstance->down();

                // Remove migration record
                $stmt = $this->db->prepare(
                    "DELETE FROM {$this->migrationsTable} WHERE migration = ?"
                );
                $stmt->execute([$migration]);
            }

            // Commit transaction
            $this->db->commit();

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Run a SQL migration file (legacy support)
     */
    private function runSqlMigration(string $migration, int $batch) {
        $filePath = $this->sqlMigrationsPath . '/' . $migration;

        if (!file_exists($filePath)) {
            throw new Exception("SQL migration file not found: {$filePath}");
        }

        // Read SQL file
        $sql = file_get_contents($filePath);

        if ($sql === false) {
            throw new Exception("Failed to read migration file: {$migration}");
        }

        // Start transaction
        $this->db->beginTransaction();

        try {
            // Execute SQL
            $this->db->exec($sql);

            // Record migration
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->migrationsTable} (migration, batch, type) VALUES (?, ?, 'sql')"
            );
            $stmt->execute([$migration, $batch]);

            // Commit transaction
            $this->db->commit();

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get list of pending migrations (both SQL and PHP)
     */
    private function getPendingMigrations() {
        // Get all migration files
        $allMigrations = array_merge(
            $this->getAllSqlMigrationFiles(),
            $this->getAllPhpMigrationFiles()
        );

        // Sort by filename (timestamp prefix)
        sort($allMigrations);

        // Get executed migrations
        $executedMigrations = $this->getExecutedMigrations();

        // Return difference
        return array_diff($allMigrations, $executedMigrations);
    }

    /**
     * Get all SQL migration files from directory
     */
    private function getAllSqlMigrationFiles() {
        if (!is_dir($this->sqlMigrationsPath)) {
            return [];
        }

        $files = scandir($this->sqlMigrationsPath);
        $migrations = [];

        foreach ($files as $file) {
            if (preg_match('/^\d{3}_.*\.sql$/', $file)) {
                $migrations[] = $file;
            }
        }

        return $migrations;
    }

    /**
     * Get all PHP migration files from directory
     */
    private function getAllPhpMigrationFiles() {
        if (!is_dir($this->phpMigrationsPath)) {
            return [];
        }

        $files = scandir($this->phpMigrationsPath);
        $migrations = [];

        foreach ($files as $file) {
            // Match pattern: YYYY_MM_DD_HHMMSS_migration_name.php
            if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_.*\.php$/', $file)) {
                $migrations[] = $file;
            }
        }

        return $migrations;
    }

    /**
     * Get migration type (sql or php)
     */
    private function getMigrationType(string $migration): string {
        return str_ends_with($migration, '.php') ? 'php' : 'sql';
    }

    /**
     * Get migration class name from filename
     * Example: 2024_01_29_120000_create_users_table.php -> CreateUsersTable
     */
    private function getMigrationClassName(string $migration): string {
        // Remove extension and timestamp prefix
        $name = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $migration);
        $name = str_replace('.php', '', $name);

        // Convert snake_case to PascalCase
        return str_replace('_', '', ucwords($name, '_'));
    }

    /**
     * Get list of executed migrations
     */
    private function getExecutedMigrations() {
        try {
            $stmt = $this->db->query("SELECT migration FROM {$this->migrationsTable} ORDER BY id");
            if ($stmt === false) {
                return [];
            }
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return $results ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get next batch number
     */
    private function getNextBatchNumber() {
        try {
            $stmt = $this->db->query("SELECT MAX(batch) FROM {$this->migrationsTable}");
            if ($stmt === false) {
                return 1;
            }
            $maxBatch = $stmt->fetchColumn();
            return ($maxBatch ?? 0) + 1;
        } catch (Exception $e) {
            return 1;
        }
    }

    /**
     * Check if migrations are pending
     */
    public function hasPending() {
        $pending = $this->getPendingMigrations();
        return !empty($pending);
    }

    /**
     * Get migration status
     */
    public function status() {
        try {
            $allMigrations = array_merge(
                $this->getAllSqlMigrationFiles(),
                $this->getAllPhpMigrationFiles()
            );
            sort($allMigrations);

            $executedMigrations = $this->getExecutedMigrations();
            $pendingMigrations = array_diff($allMigrations, $executedMigrations);

            $status = [];

            foreach ($allMigrations as $migration) {
                $type = $this->getMigrationType($migration);
                $status[] = [
                    'migration' => $migration,
                    'type' => $type,
                    'status' => in_array($migration, $executedMigrations) ? 'executed' : 'pending'
                ];
            }

            return [
                'total' => count($allMigrations),
                'executed' => count($executedMigrations),
                'pending' => count($pendingMigrations),
                'migrations' => $status
            ];

        } catch (Exception $e) {
            error_log("Migration status error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Rollback last batch of migrations
     * PHP migrations use down() method, SQL migrations just remove tracking
     */
    public function rollback() {
        try {
            $lastBatch = $this->getLastBatch();

            if (!$lastBatch) {
                return [
                    'success' => true,
                    'message' => 'Nothing to rollback'
                ];
            }

            $migrations = $this->getMigrationsByBatch($lastBatch);
            $rolledBack = [];
            $errors = [];

            foreach (array_reverse($migrations) as $row) {
                $migration = $row['migration'];
                $type = $row['type'];

                try {
                    if ($type === 'php') {
                        // Run down() method for PHP migrations
                        $this->runPhpMigration($migration, $lastBatch, 'down');
                        $rolledBack[] = $migration;
                        error_log("Rolled back (with down): {$migration}");
                    } else {
                        // SQL migrations: only remove tracking (no down support)
                        $stmt = $this->db->prepare("DELETE FROM {$this->migrationsTable} WHERE migration = ?");
                        $stmt->execute([$migration]);
                        $rolledBack[] = $migration;
                        error_log("Rolled back (tracking only): {$migration}");
                    }
                } catch (Exception $e) {
                    $errors[] = [
                        'migration' => $migration,
                        'error' => $e->getMessage()
                    ];
                    error_log("Rollback failed [{$migration}]: " . $e->getMessage());
                }
            }

            return [
                'success' => empty($errors),
                'rolled_back' => $rolledBack,
                'errors' => $errors,
                'message' => count($rolledBack) . ' migrations rolled back'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get last batch number
     */
    private function getLastBatch() {
        try {
            $stmt = $this->db->query("SELECT MAX(batch) FROM {$this->migrationsTable}");
            if ($stmt === false) {
                return null;
            }
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get migrations by batch number with type
     */
    private function getMigrationsByBatch($batch) {
        try {
            $stmt = $this->db->prepare(
                "SELECT migration, type FROM {$this->migrationsTable} WHERE batch = ? ORDER BY id"
            );
            $stmt->execute([$batch]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Reset all migrations (dangerous!)
     */
    public function reset() {
        try {
            $this->db->exec("DROP TABLE IF EXISTS {$this->migrationsTable}");
            $this->ensureMigrationsTable();

            return [
                'success' => true,
                'message' => 'Migration tracking reset. Run migrations again.'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Auto-run migrations on app startup (if enabled)
     */
    public static function autoRun() {
        try {
            $autoMigrate = env('AUTO_MIGRATE', true);

            if (!$autoMigrate) {
                return;
            }

            // Try to get instance - may fail if DB driver not available
            try {
                $migration = self::getInstance();
            } catch (Exception $e) {
                error_log("Auto-migration error: " . $e->getMessage());
                return;
            } catch (\Throwable $e) {
                error_log("Auto-migration error: " . $e->getMessage());
                return;
            }

            if ($migration->hasPending()) {
                if (app_debug()) {
                    error_log("Auto-running pending migrations...");
                }

                $result = $migration->run();

                if ($result['success']) {
                    error_log("Auto-migration completed: {$result['message']}");
                } else {
                    error_log("Auto-migration failed");
                }
            }

        } catch (Exception $e) {
            error_log("Auto-migration error: " . $e->getMessage());
        } catch (\Throwable $e) {
            error_log("Auto-migration error: " . $e->getMessage());
        }
    }
}
