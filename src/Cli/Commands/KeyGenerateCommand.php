<?php

namespace Vireo\Framework\Cli\Commands;

use Vireo\Framework\Cli\Command;

/**
 * KeyGenerateCommand - Generate application encryption key
 *
 * Generates a secure random key for application encryption and
 * optionally writes it to the .env file.
 */
class KeyGenerateCommand extends Command
{
    /**
     * Command signature
     */
    protected string $signature = 'key:generate {--show : Display the key instead of modifying .env} {--force : Force overwrite existing key}';

    /**
     * Command description
     */
    protected string $description = 'Generate a new application encryption key';

    /**
     * Execute the command
     *
     * @return int Exit code
     */
    public function handle(): int
    {
        $key = $this->generateKey();

        if ($this->option('show')) {
            $this->info('Generated key:');
            $this->line($key);
            return self::SUCCESS;
        }

        $envPath = $this->getEnvPath();

        if (!file_exists($envPath)) {
            $this->error('.env file not found at: ' . $envPath);
            $this->info('Create a .env file first or use --show to display the key.');
            return self::FAILURE;
        }

        // Check if APP_KEY already exists
        $envContent = file_get_contents($envPath);
        $hasExistingKey = preg_match('/^APP_KEY=.+$/m', $envContent);

        if ($hasExistingKey && !$this->option('force')) {
            $this->warning('Application key already exists!');
            $this->info('Use --force to overwrite the existing key.');
            $this->newLine();
            $this->info('Current key will be replaced. This may invalidate:');
            $this->line('  - Encrypted cookies');
            $this->line('  - Encrypted session data');
            $this->line('  - Any data encrypted with the old key');
            return self::FAILURE;
        }

        // Update or add APP_KEY in .env
        if ($hasExistingKey) {
            $envContent = preg_replace(
                '/^APP_KEY=.*$/m',
                'APP_KEY=' . $key,
                $envContent
            );
        } else {
            // Add APP_KEY after APP_ENV or at the beginning
            if (preg_match('/^APP_ENV=.+$/m', $envContent)) {
                $envContent = preg_replace(
                    '/^(APP_ENV=.+)$/m',
                    "$1\nAPP_KEY=" . $key,
                    $envContent
                );
            } else {
                $envContent = "APP_KEY=" . $key . "\n" . $envContent;
            }
        }

        if (file_put_contents($envPath, $envContent) === false) {
            $this->error('Failed to write to .env file');
            return self::FAILURE;
        }

        $this->success('Application key set successfully!');
        $this->newLine();
        $this->info('Key: ' . $key);

        return self::SUCCESS;
    }

    /**
     * Generate a secure random key
     *
     * @return string Base64 encoded 32-byte key
     */
    private function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    /**
     * Get the path to the .env file
     *
     * @return string
     */
    private function getEnvPath(): string
    {
        return (defined('ROOT_PATH') ? ROOT_PATH : getcwd()) . '/.env';
    }
}
