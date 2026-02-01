<?php

namespace Vireo\Framework\Email;

/**
 * Email Facade
 *
 * Provides static access to email functionality.
 */
class Email
{
    private static ?Mailer $mailer = null;

    /**
     * Get mailer instance
     */
    private static function getMailer(): Mailer
    {
        if (self::$mailer === null) {
            self::$mailer = new Mailer();
        }

        return self::$mailer;
    }

    /**
     * Create new message
     */
    public static function to(string $email, ?string $name = null): Message
    {
        $message = new Message();
        return $message->to($email, $name);
    }

    /**
     * Send message
     */
    public static function send(Message $message): array
    {
        return self::getMailer()->send($message);
    }

    /**
     * Queue message
     */
    public static function queue(Message $message, int $delay = 0): array
    {
        return self::getMailer()->queue($message, $delay);
    }

    /**
     * Create message from callback
     */
    public static function create(\Closure $callback): Message
    {
        $message = new Message();
        $callback($message);
        return $message;
    }

    /**
     * Send email using callback
     */
    public static function sendNow(\Closure $callback): array
    {
        $message = self::create($callback);
        return self::send($message);
    }

    /**
     * Queue email using callback
     */
    public static function queueNow(\Closure $callback, int $delay = 0): array
    {
        $message = self::create($callback);
        return self::queue($message, $delay);
    }

    /**
     * Get mailer instance for advanced usage
     */
    public static function mailer(): Mailer
    {
        return self::getMailer();
    }

    /**
     * Set custom mailer
     */
    public static function setMailer(Mailer $mailer): void
    {
        self::$mailer = $mailer;
    }
}
