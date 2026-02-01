<?php

/**
 * Migration Global Helper Functions
 * Provides convenient global functions for database migration management
 */

// ============== MIGRATION HELPER FUNCTIONS ==============

/**
 * Main migration helper function
 *
 * Usage:
 * migration('run')           // Run pending migrations
 * migration('status')        // Get migration status
 * migration('rollback')      // Rollback last batch
 * migration('reset')         // Reset all migrations
 * migration('pending')       // Check if pending migrations exist
 */
use Vireo\Framework\Database\Migration;

if (!function_exists('migration')) {
    function migration($action = 'status', $data = []) {
        try {
            $instance = Migration::getInstance();

            switch ($action) {
                case 'run':
                case 'migrate':
                    return $instance->run();

                case 'status':
                case 'list':
                    return $instance->status();

                case 'rollback':
                    return $instance->rollback();

                case 'reset':
                    return $instance->reset();

                case 'pending':
                case 'has_pending':
                    return $instance->hasPending();

                default:
                    throw new InvalidArgumentException("Unknown migration action: {$action}");
            }

        } catch (Exception $e) {
            error_log("Migration helper error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

/**
 * Run pending migrations
 *
 * Usage:
 * $result = run_migrations();
 * if ($result['success']) {
 *     echo "Migrated: " . count($result['migrated']) . " files";
 * }
 */
if (!function_exists('run_migrations')) {
    function run_migrations() {
        return migration('run');
    }
}

/**
 * Get migration status
 *
 * Usage:
 * $status = migration_status();
 * echo "Total: {$status['total']}, Executed: {$status['executed']}, Pending: {$status['pending']}";
 */
if (!function_exists('migration_status')) {
    function migration_status() {
        return migration('status');
    }
}

/**
 * Check if there are pending migrations
 *
 * Usage:
 * if (has_pending_migrations()) {
 *     echo "Please run migrations";
 * }
 */
if (!function_exists('has_pending_migrations')) {
    function has_pending_migrations() {
        return migration('pending');
    }
}

/**
 * Rollback last batch of migrations
 *
 * Usage:
 * $result = rollback_migrations();
 * if ($result['success']) {
 *     echo "Rolled back: " . count($result['rolled_back']) . " migrations";
 * }
 */
if (!function_exists('rollback_migrations')) {
    function rollback_migrations() {
        return migration('rollback');
    }
}

/**
 * Reset all migrations (dangerous!)
 *
 * Usage:
 * $result = reset_migrations();
 */
if (!function_exists('reset_migrations')) {
    function reset_migrations() {
        return migration('reset');
    }
}

/**
 * Display migration status in human-readable format
 *
 * Usage:
 * show_migration_status();
 */
if (!function_exists('show_migration_status')) {
    function show_migration_status() {
        $status = migration_status();

        if (empty($status)) {
            echo "Failed to get migration status\n";
            return;
        }

        echo "Migration Status:\n";
        echo "================\n";
        echo "Total migrations: {$status['total']}\n";
        echo "Executed: {$status['executed']}\n";
        echo "Pending: {$status['pending']}\n\n";

        if (!empty($status['migrations'])) {
            echo "Details:\n";
            foreach ($status['migrations'] as $migration) {
                $statusIcon = $migration['status'] === 'executed' ? '✓' : '○';
                echo "  {$statusIcon} {$migration['migration']} [{$migration['status']}]\n";
            }
        }
    }
}

/**
 * Auto-run migrations if pending
 *
 * Usage:
 * auto_migrate();  // Runs only if AUTO_MIGRATE=true
 */
if (!function_exists('auto_migrate')) {
    function auto_migrate() {
        Migration::autoRun();
    }
}

/**
 * Run migrations with output
 *
 * Usage:
 * migrate_with_output();
 */
if (!function_exists('migrate_with_output')) {
    function migrate_with_output() {
        $result = run_migrations();

        if ($result['success']) {
            echo "✓ Migration completed successfully\n";
            if (!empty($result['migrated'])) {
                echo "Migrated files:\n";
                foreach ($result['migrated'] as $migration) {
                    echo "  - {$migration}\n";
                }
            } else {
                echo $result['message'] . "\n";
            }
        } else {
            echo "✗ Migration failed\n";
            if (!empty($result['errors'])) {
                echo "Errors:\n";
                foreach ($result['errors'] as $error) {
                    echo "  - {$error['migration']}: {$error['error']}\n";
                }
            } elseif (isset($result['error'])) {
                echo "Error: {$result['error']}\n";
            }
        }

        return $result;
    }
}

// ============== MIGRATION INFO HELPERS ==============

/**
 * Get list of all migration files
 *
 * Usage:
 * $migrations = list_migrations();
 */
if (!function_exists('list_migrations')) {
    function list_migrations() {
        $status = migration_status();
        return $status['migrations'] ?? [];
    }
}

/**
 * Get only executed migrations
 *
 * Usage:
 * $executed = executed_migrations();
 */
if (!function_exists('executed_migrations')) {
    function executed_migrations() {
        $migrations = list_migrations();
        return array_filter($migrations, function($m) {
            return $m['status'] === 'executed';
        });
    }
}

/**
 * Get only pending migrations
 *
 * Usage:
 * $pending = pending_migrations();
 */
if (!function_exists('pending_migrations')) {
    function pending_migrations() {
        $migrations = list_migrations();
        return array_filter($migrations, function($m) {
            return $m['status'] === 'pending';
        });
    }
}

/**
 * Count pending migrations
 *
 * Usage:
 * $count = pending_migration_count();
 */
if (!function_exists('pending_migration_count')) {
    function pending_migration_count() {
        return count(pending_migrations());
    }
}

/**
 * Check if migrations system is healthy
 *
 * Usage:
 * if (migrations_healthy()) {
 *     echo "All migrations up to date";
 * }
 */
if (!function_exists('migrations_healthy')) {
    function migrations_healthy() {
        return !has_pending_migrations();
    }
}
