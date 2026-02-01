<?php

namespace Vireo\Framework\Cli\Commands;

use Vireo\Framework\Cli\Command;
use Vireo\Framework\Cache\CacheManager;

/**
 * CacheCommand - Cache management CLI command
 *
 * Provides commands for managing the cache system:
 * - cache:clear - Clear the default cache store
 * - cache:flush - Flush all cache stores
 */
class CacheCommand extends Command
{
    /**
     * Command signature
     */
    protected string $signature = 'cache:clear {--store=} {--all}';

    /**
     * Command description
     */
    protected string $description = 'Clear the application cache';

    /**
     * Execute the command
     *
     * @return int Exit code
     */
    public function handle(): int
    {
        $store = $this->option('store');
        $all = $this->option('all');

        try {
            $cacheManager = CacheManager::getInstance();

            if ($all) {
                // Clear all cache stores
                $this->info('Clearing all cache stores...');
                $this->newLine();

                $stores = $cacheManager->getStores();
                $cleared = 0;

                foreach ($stores as $storeName) {
                    try {
                        $cacheManager->store($storeName)->clear();
                        $this->success("✓ Cleared '{$storeName}' cache store");
                        $cleared++;
                    } catch (\Exception $e) {
                        $this->error("✗ Failed to clear '{$storeName}': " . $e->getMessage());
                    }
                }

                $this->newLine();
                $this->success("Cleared {$cleared} cache store(s)");

            } elseif ($store) {
                // Clear specific store
                $this->info("Clearing '{$store}' cache store...");

                $cacheManager->store($store)->clear();

                $this->success("✓ Cache store '{$store}' cleared successfully");

            } else {
                // Clear default store
                $defaultStore = $cacheManager->getDefaultStore();
                $this->info("Clearing default cache store ('{$defaultStore}')...");

                $cacheManager->clear();

                $this->success("✓ Default cache cleared successfully");
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Cache clear failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
