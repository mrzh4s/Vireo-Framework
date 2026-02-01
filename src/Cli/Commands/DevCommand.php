<?php

namespace Framework\Cli\Commands;

use Framework\Cli\Command;

/**
 * DevCommand - Start full development environment
 *
 * Starts both Vite (frontend) and PHP (backend) development servers.
 * This is the recommended way to run the full-stack development environment.
 */
class DevCommand extends Command
{
    /**
     * Command signature
     */
    protected string $signature = 'dev {--host=localhost} {--port=8000} {--vite-only} {--php-only}';

    /**
     * Command description
     */
    protected string $description = 'Start Vite and PHP development servers';

    /**
     * Vite process PID
     */
    private ?int $vitePid = null;

    /**
     * Execute the command
     *
     * @return int Exit code
     */
    public function handle(): int
    {
        $host = $this->option('host') ?? 'localhost';
        $port = $this->option('port') ?? '8000';
        $viteOnly = $this->option('vite-only');
        $phpOnly = $this->option('php-only');

        $this->newLine();
        $this->info('Starting Vireo Framework development environment...');
        $this->newLine();

        // Register shutdown handler to cleanup Vite process
        register_shutdown_function([$this, 'cleanup']);

        // Handle interrupt signal (Ctrl+C)
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [$this, 'handleInterrupt']);
            pcntl_signal(SIGTERM, [$this, 'handleInterrupt']);
        }

        try {
            // Start Vite (unless PHP-only mode)
            if (!$phpOnly) {
                $this->startVite();
            }

            // Start PHP server (unless Vite-only mode)
            if (!$viteOnly) {
                $this->startPhpServer($host, $port);
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Development server error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Start Vite development server
     *
     * @return void
     */
    private function startVite(): void
    {
        $this->info('Starting Vite dev server...');

        // Check if package.json exists
        if (!file_exists(ROOT_PATH . '/package.json')) {
            $this->warning('package.json not found. Skipping Vite.');
            return;
        }

        // Check if node_modules exists
        if (!is_dir(ROOT_PATH . '/node_modules')) {
            $this->warning('node_modules not found. Run: yarn install or npm install');
            $this->newLine();
        }

        // Detect package manager
        $packageManager = $this->detectPackageManager();

        // Start Vite in background
        $command = match ($packageManager) {
            'yarn' => 'yarn dev',
            'pnpm' => 'pnpm dev',
            'bun' => 'bun dev',
            default => 'npm run dev',
        };

        // Start Vite as background process
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            pclose(popen("start /B $command", "r"));
        } else {
            // Unix/Linux/Mac
            $descriptors = [
                0 => ["pipe", "r"],
                1 => ["pipe", "w"],
                2 => ["pipe", "w"]
            ];

            $process = proc_open("$command > /dev/null 2>&1 & echo $!", $descriptors, $pipes);

            if (is_resource($process)) {
                $pid = trim(fgets($pipes[1]));
                $this->vitePid = (int)$pid;

                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }
            }
        }

        $this->success("Vite dev server started ($packageManager)");

        // Give Vite time to start
        sleep(2);
        $this->newLine();
    }

    /**
     * Start PHP development server
     *
     * @param string $host Host address
     * @param string $port Port number
     * @return void
     */
    private function startPhpServer(string $host, string $port): void
    {
        $docRoot = ROOT_PATH . '/Infrastructure/Http/Public';

        if (!is_dir($docRoot)) {
            $this->error("Document root not found: {$docRoot}");
            return;
        }

        $this->info('Starting PHP dev server...');
        $this->success("Server running at: http://{$host}:{$port}");
        $this->newLine();
        $this->line('Press Ctrl+C to stop both servers');
        $this->newLine();

        // Start PHP server (blocking)
        $command = sprintf(
            'php -S %s:%s -t %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($docRoot)
        );

        passthru($command);
    }

    /**
     * Detect package manager
     *
     * @return string Package manager name
     */
    private function detectPackageManager(): string
    {
        if (file_exists(ROOT_PATH . '/yarn.lock')) {
            return 'yarn';
        }

        if (file_exists(ROOT_PATH . '/pnpm-lock.yaml')) {
            return 'pnpm';
        }

        if (file_exists(ROOT_PATH . '/bun.lockb')) {
            return 'bun';
        }

        return 'npm';
    }

    /**
     * Cleanup Vite process on shutdown
     *
     * @return void
     */
    public function cleanup(): void
    {
        if ($this->vitePid !== null) {
            $this->killProcess($this->vitePid);
        }
    }

    /**
     * Handle interrupt signal (Ctrl+C)
     *
     * @param int $signal Signal number
     * @return void
     */
    public function handleInterrupt(int $signal): void
    {
        $this->newLine();
        $this->warning('Shutting down development servers...');
        $this->cleanup();
        exit(0);
    }

    /**
     * Kill a process by PID
     *
     * @param int $pid Process ID
     * @return void
     */
    private function killProcess(int $pid): void
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            exec("taskkill /F /PID $pid 2>nul");
        } else {
            // Unix/Linux/Mac
            exec("kill $pid 2>/dev/null");
        }
    }
}
