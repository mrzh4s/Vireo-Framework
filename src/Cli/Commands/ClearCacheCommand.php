<?php

namespace Framework\Cli\Commands;

use Framework\Cli\Command;

/**
 * ClearCacheCommand - Clear application caches
 *
 * Clears various caches including compiled views, sessions, and temp files.
 */
class ClearCacheCommand extends Command
{
    /**
     * Command signature
     */
    protected string $signature = 'cache:clear {--type=all}';

    /**
     * Command description
     */
    protected string $description = 'Clear application caches';

    /**
     * Execute the command
     *
     * @return int Exit code
     */
    public function handle(): int
    {
        $type = $this->option('type');

        $this->info('Clearing caches...');
        $this->newLine();

        $cleared = [];

        switch ($type) {
            case 'all':
                $cleared = $this->clearAll();
                break;

            case 'views':
                $cleared = [$this->clearViews()];
                break;

            case 'sessions':
                $cleared = [$this->clearSessions()];
                break;

            case 'temp':
                $cleared = [$this->clearTemp()];
                break;

            default:
                $this->error("Unknown cache type: {$type}");
                $this->info("Available types: all, views, sessions, temp");
                return self::FAILURE;
        }

        // Display results
        foreach ($cleared as $result) {
            if ($result['success']) {
                $this->success("{$result['type']}: {$result['message']}");
            } else {
                $this->warning("{$result['type']}: {$result['message']}");
            }
        }

        $this->newLine();
        $this->success('Cache clearing completed!');

        return self::SUCCESS;
    }

    /**
     * Clear all caches
     *
     * @return array<int, array{type: string, success: bool, message: string}>
     */
    private function clearAll(): array
    {
        return [
            $this->clearViews(),
            $this->clearSessions(),
            $this->clearTemp(),
        ];
    }

    /**
     * Clear compiled views
     *
     * @return array{type: string, success: bool, message: string}
     */
    private function clearViews(): array
    {
        // Vireo uses Blade views - clear compiled cache if it exists
        $cachePath = ROOT_PATH . '/storage/framework/views';

        if (!is_dir($cachePath)) {
            return [
                'type' => 'Views',
                'success' => true,
                'message' => 'No view cache directory found'
            ];
        }

        $count = 0;
        $files = glob($cachePath . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                $count++;
            }
        }

        return [
            'type' => 'Views',
            'success' => true,
            'message' => "Cleared {$count} cached views"
        ];
    }

    /**
     * Clear sessions
     *
     * @return array{type: string, success: bool, message: string}
     */
    private function clearSessions(): array
    {
        $sessionPath = ROOT_PATH . '/storage/sessions';

        if (!is_dir($sessionPath)) {
            return [
                'type' => 'Sessions',
                'success' => true,
                'message' => 'No session directory found'
            ];
        }

        $count = 0;
        $files = glob($sessionPath . '/sess_*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                $count++;
            }
        }

        return [
            'type' => 'Sessions',
            'success' => true,
            'message' => "Cleared {$count} session files"
        ];
    }

    /**
     * Clear temporary files
     *
     * @return array{type: string, success: bool, message: string}
     */
    private function clearTemp(): array
    {
        $tempPath = ROOT_PATH . '/storage/temp';

        if (!is_dir($tempPath)) {
            return [
                'type' => 'Temp',
                'success' => true,
                'message' => 'No temp directory found'
            ];
        }

        $count = 0;
        $files = glob($tempPath . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                $count++;
            }
        }

        return [
            'type' => 'Temp',
            'success' => true,
            'message' => "Cleared {$count} temporary files"
        ];
    }
}
