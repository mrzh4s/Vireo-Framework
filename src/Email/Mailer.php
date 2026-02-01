<?php

namespace Vireo\Framework\Email;

use Vireo\Framework\Email\Transports\SmtpTransport;
use Vireo\Framework\Email\Template\TemplateEngine;
use Vireo\Framework\Email\Tracking\Tracker;

/**
 * Email Mailer
 *
 * Main class for sending emails.
 */
class Mailer
{
    private SmtpTransport $transport;
    private TemplateEngine $templateEngine;
    private ?Tracker $tracker = null;
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? config('email');
        $this->transport = new SmtpTransport($this->config);
        $this->templateEngine = new TemplateEngine();

        if ($this->config['tracking']['enabled'] ?? false) {
            $this->tracker = new Tracker();
        }
    }

    /**
     * Send email immediately
     */
    public function send(Message $message): array
    {
        try {
            $message->validate();

            // Render view if specified
            if ($message->getView()) {
                $rendered = $this->templateEngine->render(
                    $message->getView(),
                    $message->getViewData()
                );
                $message->html($rendered['html']);
                if (!$message->getText()) {
                    $message->text($rendered['text']);
                }
            }

            // Store in database
            $emailId = $this->storeEmail($message);

            // Inject tracking if enabled
            if ($this->tracker && $message->getHtml()) {
                $trackedHtml = $this->tracker->injectTracking($emailId, $message->getHtml());
                $message->html($trackedHtml);
            }

            // Send via transport
            $this->transport->send($message);

            // Mark as sent
            $this->markAsSent($emailId);

            // Log event
            if ($this->tracker) {
                $this->tracker->trackSent($emailId);
            }

            logger('email')->info("Email sent successfully", [
                'email_id' => $emailId,
                'to' => array_column($message->getTo(), 'email'),
                'subject' => $message->getSubject(),
            ]);

            return [
                'success' => true,
                'email_id' => $emailId,
                'message' => 'Email sent successfully',
            ];
        } catch (\Exception $e) {
            logger('email')->error("Failed to send email", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if (isset($emailId)) {
                $this->markAsFailed($emailId, $e->getMessage());
            }

            throw $e;
        }
    }

    /**
     * Queue email for later sending
     */
    public function queue(Message $message, int $delay = 0): array
    {
        try {
            $message->validate();

            // Store in database with pending status
            $emailId = $this->storeEmail($message, 'pending', $delay);

            logger('email')->info("Email queued successfully", [
                'email_id' => $emailId,
                'to' => array_column($message->getTo(), 'email'),
                'delay' => $delay,
            ]);

            return [
                'success' => true,
                'email_id' => $emailId,
                'message' => 'Email queued successfully',
            ];
        } catch (\Exception $e) {
            logger('email')->error("Failed to queue email", [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Store email in database
     */
    private function storeEmail(Message $message, string $status = 'processing', int $delay = 0): int
    {
        $config = config('email');
        $from = !empty($message->getFrom()) ? $message->getFrom() : [
            'email' => $config['from']['address'],
            'name' => $config['from']['name'] ?? null,
        ];

        $data = [
            'from_address' => $from['email'],
            'from_name' => $from['name'],
            'to_addresses' => json_encode($message->getTo()),
            'cc_addresses' => json_encode($message->getCc()),
            'bcc_addresses' => json_encode($message->getBcc()),
            'reply_to' => !empty($message->getReplyTo()) ? $message->getReplyTo()['email'] : null,
            'subject' => $message->getSubject(),
            'body_html' => $message->getHtml(),
            'body_text' => $message->getText(),
            'priority' => $message->getPriority(),
            'status' => $status,
            'campaign_id' => $message->getCampaignId(),
            'tags' => json_encode($message->getTags()),
            'metadata' => json_encode($message->getMetadata()),
            'attempts' => 0,
            'max_attempts' => $config['queue']['max_attempts'] ?? 3,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($delay > 0) {
            $data['scheduled_at'] = date('Y-m-d H:i:s', time() + $delay);
        }

        $emailId = table('emails_queue')->insertGetId($data);

        // Store attachments
        foreach ($message->getAttachments() as $attachment) {
            $this->storeAttachment($emailId, $attachment);
        }

        return $emailId;
    }

    /**
     * Store attachment
     */
    private function storeAttachment(int $emailId, array $attachment): void
    {
        // Handle file path or data
        if (isset($attachment['path'])) {
            $path = $attachment['path'];
            $name = $attachment['name'];
            $size = filesize($path);
            $mime = mime_content_type($path);

            // Copy to storage
            $storagePath = 'email/attachments/' . date('Y/m/d') . '/' . uniqid() . '_' . $name;
            storage('local')->putFile($storagePath, $path);

            table('email_attachments')->insert([
                'email_id' => $emailId,
                'filename' => basename($storagePath),
                'original_filename' => $name,
                'mime_type' => $mime,
                'size' => $size,
                'storage_disk' => 'local',
                'storage_path' => $storagePath,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Mark email as sent
     */
    private function markAsSent(int $emailId): void
    {
        table('emails_queue')
            ->where('id', $emailId)
            ->update([
                'status' => 'sent',
                'sent_at' => date('Y-m-d H:i:s'),
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
     * Get transport
     */
    public function getTransport(): SmtpTransport
    {
        return $this->transport;
    }
}
