<?php

namespace Framework\Email\Transports;

use Framework\Email\Message;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email as SymfonyEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

/**
 * SMTP Transport
 *
 * Sends emails via SMTP using Symfony Mailer.
 */
class SmtpTransport
{
    private SymfonyMailer $mailer;
    private array $config;
    private int $emailsSent = 0;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->initialize();
    }

    /**
     * Initialize Symfony Mailer
     */
    private function initialize(): void
    {
        $mailerName = $this->config['default'] ?? 'smtp';
        $mailerConfig = $this->config['mailers'][$mailerName] ?? null;

        if (!$mailerConfig) {
            throw new \RuntimeException("Mailer '{$mailerName}' not configured");
        }

        $dsn = $this->buildDsn($mailerConfig);
        $transport = Transport::fromDsn($dsn);
        $this->mailer = new SymfonyMailer($transport);
    }

    /**
     * Build DSN from config
     */
    private function buildDsn(array $config): string
    {
        $host = $config['host'];
        $port = $config['port'];
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';

        if ($username && $password) {
            return "smtp://{$username}:{$password}@{$host}:{$port}";
        }

        return "smtp://{$host}:{$port}";
    }

    /**
     * Send email
     */
    public function send(Message $message): void
    {
        $symfonyMessage = $this->buildSymfonyMessage($message);
        $this->mailer->send($symfonyMessage);

        $this->emailsSent++;
        $this->checkConnectionLimit();
    }

    /**
     * Build Symfony Email message
     */
    private function buildSymfonyMessage(Message $message): SymfonyEmail
    {
        $email = new SymfonyEmail();

        // From
        $from = $message->getFrom();
        if (!empty($from)) {
            $email->from(new Address($from['email'], $from['name'] ?? ''));
        } else {
            $defaultFrom = $this->config['from'];
            $email->from(new Address($defaultFrom['address'], $defaultFrom['name'] ?? ''));
        }

        // To
        foreach ($message->getTo() as $to) {
            $email->addTo(new Address($to['email'], $to['name'] ?? ''));
        }

        // CC
        foreach ($message->getCc() as $cc) {
            $email->addCc(new Address($cc['email'], $cc['name'] ?? ''));
        }

        // BCC
        foreach ($message->getBcc() as $bcc) {
            $email->addBcc(new Address($bcc['email'], $bcc['name'] ?? ''));
        }

        // Reply-To
        if (!empty($message->getReplyTo())) {
            $replyTo = $message->getReplyTo();
            $email->replyTo(new Address($replyTo['email'], $replyTo['name'] ?? ''));
        }

        // Subject
        $email->subject($message->getSubject());

        // Body
        if ($message->getHtml()) {
            $email->html($message->getHtml());
        }

        if ($message->getText()) {
            $email->text($message->getText());
        } elseif ($message->getHtml()) {
            // Auto-generate plain text
            $email->text($this->htmlToText($message->getHtml()));
        }

        // Priority
        $priority = match($message->getPriority()) {
            'urgent' => 1,
            'high' => 2,
            'normal' => 3,
            'low' => 4,
            default => 3,
        };
        $email->priority($priority);

        // Attachments
        foreach ($message->getAttachments() as $attachment) {
            if (isset($attachment['path']) && file_exists($attachment['path'])) {
                $email->attachFromPath($attachment['path'], $attachment['name'] ?? null);
            } elseif (isset($attachment['data'])) {
                $email->attach($attachment['data'], $attachment['name'], $attachment['mime'] ?? null);
            }
        }

        // Custom headers
        foreach ($this->config['headers'] ?? [] as $name => $value) {
            $email->getHeaders()->addTextHeader($name, $value);
        }

        return $email;
    }

    /**
     * Convert HTML to plain text
     */
    private function htmlToText(string $html): string
    {
        $text = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $text = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $text);
        $text = preg_replace('/<a\s+href="([^"]+)"[^>]*>(.*?)<\/a>/i', '$2 ($1)', $text);
        $text = str_replace(['<br>', '<br/>', '<br />'], "\n", $text);
        $text = preg_replace('/<\/p>/i', "\n\n", $text);
        $text = preg_replace('/<li>/i', "• ", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }

    /**
     * Check connection limit
     */
    private function checkConnectionLimit(): void
    {
        $limit = $this->config['rate_limit']['per_connection'] ?? 100;

        if ($this->emailsSent >= $limit) {
            $this->reconnect();
        }
    }

    /**
     * Reconnect
     */
    private function reconnect(): void
    {
        $this->initialize();
        $this->emailsSent = 0;
    }
}
