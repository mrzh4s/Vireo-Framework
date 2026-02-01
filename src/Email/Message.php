<?php

namespace Framework\Email;

/**
 * Email Message
 *
 * Represents an email message with fluent builder interface.
 */
class Message
{
    private array $from = [];
    private array $to = [];
    private array $cc = [];
    private array $bcc = [];
    private array $replyTo = [];
    private string $subject = '';
    private ?string $html = null;
    private ?string $text = null;
    private ?string $view = null;
    private array $viewData = [];
    private array $attachments = [];
    private string $priority = 'normal';
    private ?int $campaignId = null;
    private array $tags = [];
    private array $metadata = [];

    /**
     * Set from address
     */
    public function from(string $email, ?string $name = null): self
    {
        $this->from = ['email' => $email, 'name' => $name];
        return $this;
    }

    /**
     * Add recipient
     */
    public function to(string $email, ?string $name = null): self
    {
        $this->to[] = ['email' => $email, 'name' => $name];
        return $this;
    }

    /**
     * Add CC recipient
     */
    public function cc(string $email, ?string $name = null): self
    {
        $this->cc[] = ['email' => $email, 'name' => $name];
        return $this;
    }

    /**
     * Add BCC recipient
     */
    public function bcc(string $email, ?string $name = null): self
    {
        $this->bcc[] = ['email' => $email, 'name' => $name];
        return $this;
    }

    /**
     * Set reply-to address
     */
    public function replyTo(string $email, ?string $name = null): self
    {
        $this->replyTo = ['email' => $email, 'name' => $name];
        return $this;
    }

    /**
     * Set subject
     */
    public function subject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Set HTML body
     */
    public function html(string $html): self
    {
        $this->html = $html;
        return $this;
    }

    /**
     * Set plain text body
     */
    public function text(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    /**
     * Set view template
     */
    public function view(string $view, array $data = []): self
    {
        $this->view = $view;
        $this->viewData = $data;
        return $this;
    }

    /**
     * Attach file
     */
    public function attach(string $path, ?string $name = null): self
    {
        $this->attachments[] = [
            'path' => $path,
            'name' => $name ?? basename($path),
        ];
        return $this;
    }

    /**
     * Attach from data
     */
    public function attachData(string $data, string $name, string $mimeType = 'application/octet-stream'): self
    {
        $this->attachments[] = [
            'data' => $data,
            'name' => $name,
            'mime' => $mimeType,
        ];
        return $this;
    }

    /**
     * Set priority
     */
    public function priority(string $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * Set campaign
     */
    public function campaign(int $campaignId): self
    {
        $this->campaignId = $campaignId;
        return $this;
    }

    /**
     * Add tag
     */
    public function tag(string $tag): self
    {
        $this->tags[] = $tag;
        return $this;
    }

    /**
     * Set metadata
     */
    public function metadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    // Getters
    public function getFrom(): array { return $this->from; }
    public function getTo(): array { return $this->to; }
    public function getCc(): array { return $this->cc; }
    public function getBcc(): array { return $this->bcc; }
    public function getReplyTo(): array { return $this->replyTo; }
    public function getSubject(): string { return $this->subject; }
    public function getHtml(): ?string { return $this->html; }
    public function getText(): ?string { return $this->text; }
    public function getView(): ?string { return $this->view; }
    public function getViewData(): array { return $this->viewData; }
    public function getAttachments(): array { return $this->attachments; }
    public function getPriority(): string { return $this->priority; }
    public function getCampaignId(): ?int { return $this->campaignId; }
    public function getTags(): array { return $this->tags; }
    public function getMetadata(): array { return $this->metadata; }

    /**
     * Validate message
     */
    public function validate(): void
    {
        if (empty($this->to)) {
            throw new \InvalidArgumentException('At least one recipient is required');
        }

        if (empty($this->subject)) {
            throw new \InvalidArgumentException('Subject is required');
        }

        if (empty($this->html) && empty($this->text) && empty($this->view)) {
            throw new \InvalidArgumentException('Email body is required (html, text, or view)');
        }
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'reply_to' => $this->replyTo,
            'subject' => $this->subject,
            'html' => $this->html,
            'text' => $this->text,
            'view' => $this->view,
            'view_data' => $this->viewData,
            'attachments' => $this->attachments,
            'priority' => $this->priority,
            'campaign_id' => $this->campaignId,
            'tags' => $this->tags,
            'metadata' => $this->metadata,
        ];
    }
}
