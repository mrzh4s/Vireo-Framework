<?php

namespace Framework\Email\Queue;

use Framework\Email\Message;
use Framework\Email\Mailer;

/**
 * Email Queue
 *
 * Manages email queue processing.
 */
class EmailQueue
{
    private Mailer $mailer;
    private array $config;

    public function __construct(?Mailer $mailer = null)
    {
        $this->mailer = $mailer ?? new Mailer();
        $this->config = config('email');
    }

    /**
     * Process email queue
     */
    public function process(int $batchSize = 100): array
    {
        $stats = [
            'sent' => 0,
            'failed' => 0,
            'pending' => 0,
        ];

        // Get pending emails
        $emails = $this->getPendingEmails($batchSize);

        foreach ($emails as $emailData) {
            try {
                // Mark as processing
                $this->markAsProcessing($emailData['id']);

                // Build message
                $message = $this->buildMessage($emailData);

                // Send
                $this->mailer->send($message);

                $stats['sent']++;
            } catch (\Exception $e) {
                // Increment attempts
                $attempts = $emailData['attempts'] + 1;
                $maxAttempts = $emailData['max_attempts'];

                if ($attempts < $maxAttempts) {
                    // Retry with exponential backoff
                    $delay = $this->calculateRetryDelay($attempts);
                    $this->scheduleRetry($emailData['id'], $attempts, $delay);
                    $stats['pending']++;
                } else {
                    // Max attempts reached
                    $this->markAsFailed($emailData['id'], $e->getMessage());
                    $stats['failed']++;
                }

                logger('email')->warning("Email send failed", [
                    'email_id' => $emailData['id'],
                    'attempts' => $attempts,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Get pending emails
     */
    private function getPendingEmails(int $limit): array
    {
        return table('emails_queue')
            ->where('status', 'pending')
            ->where(function($query) {
                $query->whereNull('scheduled_at')
                      ->orWhere('scheduled_at', '<=', date('Y-m-d H:i:s'));
            })
            ->whereRaw('attempts < max_attempts')
            ->orderByRaw("
                CASE priority
                    WHEN 'urgent' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'normal' THEN 3
                    WHEN 'low' THEN 4
                END
            ")
            ->orderBy('created_at', 'ASC')
            ->limit($limit)
            ->get();
    }

    /**
     * Build message from database record
     */
    private function buildMessage(array $data): Message
    {
        $message = new Message();

        // From
        if ($data['from_address']) {
            $message->from($data['from_address'], $data['from_name']);
        }

        // To
        $toAddresses = json_decode($data['to_addresses'], true) ?? [];
        foreach ($toAddresses as $to) {
            $message->to($to['email'], $to['name'] ?? null);
        }

        // CC
        $ccAddresses = json_decode($data['cc_addresses'], true) ?? [];
        foreach ($ccAddresses as $cc) {
            $message->cc($cc['email'], $cc['name'] ?? null);
        }

        // BCC
        $bccAddresses = json_decode($data['bcc_addresses'], true) ?? [];
        foreach ($bccAddresses as $bcc) {
            $message->bcc($bcc['email'], $bcc['name'] ?? null);
        }

        // Reply-To
        if ($data['reply_to']) {
            $message->replyTo($data['reply_to']);
        }

        // Subject and body
        $message->subject($data['subject']);

        if ($data['body_html']) {
            $message->html($data['body_html']);
        }

        if ($data['body_text']) {
            $message->text($data['body_text']);
        }

        // Priority
        $message->priority($data['priority']);

        // Campaign
        if ($data['campaign_id']) {
            $message->campaign($data['campaign_id']);
        }

        // Tags
        if ($data['tags']) {
            $tags = json_decode($data['tags'], true) ?? [];
            foreach ($tags as $tag) {
                $message->tag($tag);
            }
        }

        // Load attachments
        $attachments = table('email_attachments')
            ->where('email_id', $data['id'])
            ->get();

        foreach ($attachments as $attachment) {
            $path = storage($attachment['storage_disk'])->path($attachment['storage_path']);
            if (file_exists($path)) {
                $message->attach($path, $attachment['original_filename']);
            }
        }

        return $message;
    }

    /**
     * Mark email as processing
     */
    private function markAsProcessing(int $emailId): void
    {
        table('emails_queue')
            ->where('id', $emailId)
            ->update([
                'status' => 'processing',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Schedule retry with exponential backoff
     */
    private function scheduleRetry(int $emailId, int $attempts, int $delay): void
    {
        table('emails_queue')
            ->where('id', $emailId)
            ->update([
                'status' => 'pending',
                'attempts' => $attempts,
                'scheduled_at' => date('Y-m-d H:i:s', time() + $delay),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Mark email as failed
     */
    private function markAsFailed(int $emailId, string $error): void
    {
        table('emails_queue')
            ->where('id', $emailId)
            ->update([
                'status' => 'failed',
                'last_error' => $error,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Calculate retry delay (exponential backoff)
     */
    private function calculateRetryDelay(int $attempt): int
    {
        $baseDelay = $this->config['queue']['retry_delay'] ?? 60;
        return $baseDelay * pow(2, $attempt - 1);
    }

    /**
     * Get queue depth
     */
    public function depth(): int
    {
        return table('emails_queue')
            ->where('status', 'pending')
            ->where(function($query) {
                $query->whereNull('scheduled_at')
                      ->orWhere('scheduled_at', '<=', date('Y-m-d H:i:s'));
            })
            ->count();
    }

    /**
     * Cleanup old emails
     */
    public function cleanup(int $days = 30): int
    {
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return table('emails_queue')
            ->where('status', 'sent')
            ->where('created_at', '<', $date)
            ->delete();
    }
}
