<?php

namespace Vireo\Framework\Cli\Commands;

use Vireo\Framework\Cli\Command;

/**
 * ServeCommand - Start PHP development server
 *
 * Starts the built-in PHP development server for local development.
 */
class ServeCommand extends Command
{
    /**
     * Command signature
     */
    protected string $signature = 'serve {--host=localhost} {--port=8000}';

    /**
     * Command description
     */
    protected string $description = 'Start the PHP development server';

    /**
     * Execute the command
     *
     * @return int Exit code
     */
    public function handle(): int
    {
        $host = $this->option('host');
        $port = $this->option('port');
        $docRoot = ROOT_PATH . '/Infrastructure/Http/Public';

        // Check if document root exists
        if (!is_dir($docRoot)) {
            $this->error("Document root not found: {$docRoot}");
            return self::FAILURE;
        }

        // Check if port is already in use
        $socket = @fsockopen($host, (int)$port, $errno, $errstr, 1);
        if ($socket !== false) {
            fclose($socket);
            $this->error("Port {$port} is already in use.");
            $this->info("Try a different port: ./vireo serve --port=" . ((int)$port + 1));
            return self::FAILURE;
        }

        // Display server information
        $this->newLine();
        $this->success("Vireo development server started");
        $this->info("Server running at: http://{$host}:{$port}");
        $this->info("Document root: {$docRoot}");
        $this->newLine();
        $this->line("Press Ctrl+C to stop the server");
        $this->newLine();

        // Build command
        $command = sprintf(
            'php -S %s:%s -t %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($docRoot)
        );

        // Start server using passthru for real-time output
        passthru($command, $exitCode);

        return $exitCode;
    }
}
