<?php

use Vireo\Framework\Email\Email;
use Vireo\Framework\Email\Message;
use Vireo\Framework\Email\Queue\EmailQueue;

if (!function_exists('email')) {
    /**
     * Send email (simple helper)
     *
     * @param string|array $to Recipient email address(es)
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param string|null $from From address (optional)
     * @return array Result
     */
    function email(string|array $to, string $subject, string $body, ?string $from = null): array
    {
        $message = new Message();

        // Set from
        if ($from) {
            $message->from($from);
        }

        // Set recipients
        $recipients = is_array($to) ? $to : [$to];
        foreach ($recipients as $recipient) {
            if (is_string($recipient)) {
                $message->to($recipient);
            } elseif (is_array($recipient)) {
                $message->to($recipient['email'], $recipient['name'] ?? null);
            }
        }

        // Set content
        $message->subject($subject)->html($body);

        return Email::send($message);
    }
}

if (!function_exists('email_queue')) {
    /**
     * Queue email for later sending
     *
     * @param string|array $to Recipient email address(es)
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param int $delay Delay in seconds (optional)
     * @return array Result
     */
    function email_queue(string|array $to, string $subject, string $body, int $delay = 0): array
    {
        $message = new Message();

        // Set recipients
        $recipients = is_array($to) ? $to : [$to];
        foreach ($recipients as $recipient) {
            if (is_string($recipient)) {
                $message->to($recipient);
            } elseif (is_array($recipient)) {
                $message->to($recipient['email'], $recipient['name'] ?? null);
            }
        }

        // Set content
        $message->subject($subject)->html($body);

        return Email::queue($message, $delay);
    }
}

if (!function_exists('email_view')) {
    /**
     * Send email using a view template
     *
     * @param string|array $to Recipient email address(es)
     * @param string $subject Email subject
     * @param string $view View name (e.g., 'emails.welcome')
     * @param array $data View data
     * @param bool $queue Queue for later sending
     * @return array Result
     */
    function email_view(string|array $to, string $subject, string $view, array $data = [], bool $queue = false): array
    {
        $message = new Message();

        // Set recipients
        $recipients = is_array($to) ? $to : [$to];
        foreach ($recipients as $recipient) {
            if (is_string($recipient)) {
                $message->to($recipient);
            } elseif (is_array($recipient)) {
                $message->to($recipient['email'], $recipient['name'] ?? null);
            }
        }

        // Set content
        $message->subject($subject)->view($view, $data);

        return $queue ? Email::queue($message) : Email::send($message);
    }
}

if (!function_exists('email_process_queue')) {
    /**
     * Process email queue
     *
     * @param int $batchSize Number of emails to process
     * @return array Statistics
     */
    function email_process_queue(int $batchSize = 100): array
    {
        $queue = new EmailQueue();
        return $queue->process($batchSize);
    }
}

if (!function_exists('email_queue_depth')) {
    /**
     * Get email queue depth (pending count)
     *
     * @return int Number of pending emails
     */
    function email_queue_depth(): int
    {
        $queue = new EmailQueue();
        return $queue->depth();
    }
}

if (!function_exists('email_cleanup')) {
    /**
     * Cleanup old sent emails
     *
     * @param int $days Delete emails older than this many days
     * @return int Number of emails deleted
     */
    function email_cleanup(int $days = 30): int
    {
        $queue = new EmailQueue();
        return $queue->cleanup($days);
    }
}
