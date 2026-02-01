<?php

namespace Vireo\Framework\Email\Template;

use Vireo\Framework\View\Blade;

/**
 * Template Engine
 *
 * Renders email templates using Blade.
 */
class TemplateEngine
{
    private Blade $blade;
    private array $config;

    public function __construct()
    {
        $this->config = config('email.templates');
        $this->blade = new Blade(
            $this->config['path'] ?? resource_path('views/email'),
            storage_path('framework/views')
        );
    }

    /**
     * Render template
     */
    public function render(string $view, array $data = []): array
    {
        // Merge with global variables
        $data = array_merge($this->config['global_vars'] ?? [], $data);

        try {
            // Render HTML
            $html = $this->blade->render($view, $data);

            // Generate plain text
            $text = $this->htmlToText($html);

            return [
                'html' => $html,
                'text' => $text,
            ];
        } catch (\Exception $e) {
            logger('email')->error("Failed to render template", [
                'view' => $view,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
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
        $text = preg_replace('/<\/div>/i', "\n", $text);
        $text = preg_replace('/<\/h[1-6]>/i', "\n\n", $text);
        $text = preg_replace('/<li>/i', "• ", $text);
        $text = preg_replace('/<\/li>/i', "\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s+/', "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }
}
