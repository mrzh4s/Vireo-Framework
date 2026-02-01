<?php

namespace Framework\Cli\Commands;

use Framework\Cli\Command;
use Framework\Email\Queue\EmailQueue;

/**
 * Email Queue Process Command
 *
 * CLI command to process the email queue.
 */
class EmailQueueProcessCommand extends Command
{
    protected string $signature = 'email:queue:process {--daemon : Run as daemon} {--once : Process one batch only} {--limit=100 : Batch size}';
    protected string $description = 'Process the email queue';

    public function handle(): int
    {
        $daemon = $this->option('daemon');
        $once = $this->option('once');
        $limit = (int)$this->option('limit');

        $this->info("Processing email queue...");

        if ($daemon) {
            $this->info("Running in daemon mode (press Ctrl+C to stop)");
        }

        $queue = new EmailQueue();
        $startTime = time();
        $totalStats = ['sent' => 0, 'failed' => 0, 'pending' => 0];

        do {
            $stats = $queue->process($limit);

            $totalStats['sent'] += $stats['sent'];
            $totalStats['failed'] += $stats['failed'];
            $totalStats['pending'] += $stats['pending'];

            $this->line("Sent: {$stats['sent']}, Failed: {$stats['failed']}, Pending: {$stats['pending']}");

            if ($daemon && !$once) {
                $queueDepth = $queue->depth();

                if ($queueDepth === 0) {
                    $this->line("Queue empty, sleeping...");
                    sleep(5);
                } else {
                    sleep(1);
                }

                // Check max runtime (1 hour)
                if ((time() - $startTime) >= 3600) {
                    $this->info("Max runtime reached, stopping");
                    break;
                }
            } else {
                break;
            }
        } while (true);

        $this->success("Queue processing completed");
        $this->line("Total - Sent: {$totalStats['sent']}, Failed: {$totalStats['failed']}, Pending: {$totalStats['pending']}");

        return 0;
    }
}
