<?php

namespace Vireo\Framework\Email\Tracking;

/**
 * Email Tracker
 *
 * Handles email tracking (opens, clicks).
 */
class Tracker
{
    private array $config;

    public function __construct()
    {
        $this->config = config('email.tracking');
    }

    /**
     * Inject tracking into HTML
     */
    public function injectTracking(int $emailId, string $html): string
    {
        if (!$this->config['enabled']) {
            return $html;
        }

        // Inject open tracking pixel
        if ($this->config['track_opens']) {
            $html = $this->injectOpenPixel($emailId, $html);
        }

        // Inject click tracking
        if ($this->config['track_clicks']) {
            $html = $this->injectClickTracking($emailId, $html);
        }

        return $html;
    }

    /**
     * Inject open tracking pixel
     */
    private function injectOpenPixel(int $emailId, string $html): string
    {
        // Create or get pixel token
        $token = $this->getOrCreatePixelToken($emailId);

        // Build pixel URL
        $pixelUrl = $this->config['pixel_url'] ?? (config('app.url') . '/email/track/open');
        $pixel = "<img src=\"{$pixelUrl}/{$token}\" width=\"1\" height=\"1\" alt=\"\" style=\"display:none;\" />";

        // Insert before closing body tag
        if (stripos($html, '</body>') !== false) {
            $html = str_ireplace('</body>', $pixel . '</body>', $html);
        } else {
            $html .= $pixel;
        }

        return $html;
    }

    /**
     * Inject click tracking
     */
    private function injectClickTracking(int $emailId, string $html): string
    {
        $pattern = '/<a\s+[^>]*href="([^"]+)"[^>]*>/i';

        $html = preg_replace_callback($pattern, function($matches) use ($emailId) {
            $fullTag = $matches[0];
            $originalUrl = $matches[1];

            // Skip certain URLs
            if ($this->shouldSkipTracking($originalUrl)) {
                return $fullTag;
            }

            // Create tracking link
            $trackingUrl = $this->createTrackingLink($emailId, $originalUrl);

            // Replace URL
            return str_replace($originalUrl, $trackingUrl, $fullTag);
        }, $html);

        return $html;
    }

    /**
     * Should skip tracking for URL
     */
    private function shouldSkipTracking(string $url): bool
    {
        return str_starts_with($url, '#') ||
               str_starts_with($url, 'mailto:') ||
               str_contains($url, '/unsubscribe') ||
               str_contains($url, '/track/');
    }

    /**
     * Get or create pixel token
     */
    private function getOrCreatePixelToken(int $emailId): string
    {
        $existing = table('email_tracking_pixels')
            ->where('email_id', $emailId)
            ->first();

        if ($existing) {
            return $existing['pixel_token'];
        }

        $token = hash('sha256', $emailId . microtime() . random_bytes(16));

        table('email_tracking_pixels')->insert([
            'email_id' => $emailId,
            'pixel_token' => $token,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $token;
    }

    /**
     * Create tracking link
     */
    private function createTrackingLink(int $emailId, string $originalUrl): string
    {
        $token = hash('sha256', $emailId . $originalUrl . microtime() . random_bytes(16));

        table('email_link_tracking')->insert([
            'email_id' => $emailId,
            'original_url' => $originalUrl,
            'tracking_token' => $token,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $baseUrl = $this->config['click_url'] ?? (config('app.url') . '/email/track/click');
        return "{$baseUrl}/{$token}";
    }

    /**
     * Track email sent
     */
    public function trackSent(int $emailId): void
    {
        table('email_tracking')->insert([
            'email_id' => $emailId,
            'event_type' => 'sent',
            'tracked_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Track email open
     */
    public function trackOpen(string $pixelToken): bool
    {
        $pixel = table('email_tracking_pixels')
            ->where('pixel_token', $pixelToken)
            ->first();

        if (!$pixel) {
            return false;
        }

        $emailId = $pixel['email_id'];
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        // Check if already tracked recently (prevent duplicates)
        $recent = table('email_tracking')
            ->where('email_id', $emailId)
            ->where('event_type', 'opened')
            ->where('ip_address', $ip)
            ->where('tracked_at', '>=', date('Y-m-d H:i:s', strtotime('-1 hour')))
            ->first();

        if ($recent) {
            return false;
        }

        table('email_tracking')->insert([
            'email_id' => $emailId,
            'event_type' => 'opened',
            'ip_address' => $ip,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'tracked_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * Track email click
     */
    public function trackClick(string $trackingToken): ?string
    {
        $link = table('email_link_tracking')
            ->where('tracking_token', $trackingToken)
            ->first();

        if (!$link) {
            return null;
        }

        // Increment click count
        table('email_link_tracking')
            ->where('id', $link['id'])
            ->update([
                'click_count' => $link['click_count'] + 1,
                'last_clicked_at' => date('Y-m-d H:i:s'),
            ]);

        if (!$link['first_clicked_at']) {
            table('email_link_tracking')
                ->where('id', $link['id'])
                ->update(['first_clicked_at' => date('Y-m-d H:i:s')]);
        }

        // Track event
        table('email_tracking')->insert([
            'email_id' => $link['email_id'],
            'event_type' => 'clicked',
            'event_data' => json_encode(['url' => $link['original_url']]),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'tracked_at' => date('Y-m-d H:i:s'),
        ]);

        return $link['original_url'];
    }

    /**
     * Get email stats
     */
    public function getStats(int $emailId): array
    {
        $stats = [
            'sent' => $this->countEvents($emailId, 'sent'),
            'opened' => $this->countEvents($emailId, 'opened'),
            'clicked' => $this->countEvents($emailId, 'clicked'),
            'bounced' => $this->countEvents($emailId, 'bounced'),
        ];

        return $stats;
    }

    /**
     * Count events
     */
    private function countEvents(int $emailId, string $eventType): int
    {
        return table('email_tracking')
            ->where('email_id', $emailId)
            ->where('event_type', $eventType)
            ->count();
    }
}
