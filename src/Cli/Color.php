<?php

namespace Vireo\Framework\Cli;

/**
 * Color - Native ANSI color code implementation
 *
 * Provides color and styling support for terminal output using ANSI escape codes.
 * Zero external dependencies - pure PHP implementation.
 */
class Color
{
    /**
     * Foreground color codes
     */
    private const COLORS = [
        'black' => '30',
        'red' => '31',
        'green' => '32',
        'yellow' => '33',
        'blue' => '34',
        'magenta' => '35',
        'cyan' => '36',
        'white' => '37',
        'default' => '39',
    ];

    /**
     * Background color codes
     */
    private const BG_COLORS = [
        'black' => '40',
        'red' => '41',
        'green' => '42',
        'yellow' => '43',
        'blue' => '44',
        'magenta' => '45',
        'cyan' => '46',
        'white' => '47',
        'default' => '49',
    ];

    /**
     * Text style codes
     */
    private const STYLES = [
        'bold' => '1',
        'dim' => '2',
        'underline' => '4',
        'blink' => '5',
        'reverse' => '7',
        'hidden' => '8',
    ];

    /**
     * Apply ANSI color codes to text
     *
     * @param string $text Text to colorize
     * @param array<string> $formats Array of format names (color, background, style)
     * @return string Formatted text with ANSI codes
     */
    public function apply(string $text, array $formats): string
    {
        if (!$this->isSupported()) {
            return $text;
        }

        $codes = [];
        foreach ($formats as $format) {
            if (isset(self::COLORS[$format])) {
                $codes[] = self::COLORS[$format];
            } elseif (isset(self::BG_COLORS[$format])) {
                $codes[] = self::BG_COLORS[$format];
            } elseif (isset(self::STYLES[$format])) {
                $codes[] = self::STYLES[$format];
            }
        }

        if (empty($codes)) {
            return $text;
        }

        return sprintf("\033[%sm%s\033[0m", implode(';', $codes), $text);
    }

    /**
     * Check if terminal supports ANSI colors
     *
     * @return bool True if colors are supported
     */
    public function isSupported(): bool
    {
        // Check if output is being redirected or piped
        if (function_exists('stream_isatty') && !stream_isatty(STDOUT)) {
            return false;
        }

        // Alternative check using posix_isatty
        if (function_exists('posix_isatty') && !posix_isatty(STDOUT)) {
            return false;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            // Windows
            return getenv('ANSICON') !== false
                || getenv('ConEmuANSI') === 'ON'
                || getenv('TERM_PROGRAM') === 'Hyper'
                || getenv('TERM_PROGRAM') === 'vscode'
                || getenv('WT_SESSION') !== false; // Windows Terminal
        }

        // Unix/Linux/Mac
        return true;
    }

    /**
     * Apply red color to text
     *
     * @param string $text Text to colorize
     * @return string Red text
     */
    public function red(string $text): string
    {
        return $this->apply($text, ['red']);
    }

    /**
     * Apply green color to text
     *
     * @param string $text Text to colorize
     * @return string Green text
     */
    public function green(string $text): string
    {
        return $this->apply($text, ['green']);
    }

    /**
     * Apply yellow color to text
     *
     * @param string $text Text to colorize
     * @return string Yellow text
     */
    public function yellow(string $text): string
    {
        return $this->apply($text, ['yellow']);
    }

    /**
     * Apply blue color to text
     *
     * @param string $text Text to colorize
     * @return string Blue text
     */
    public function blue(string $text): string
    {
        return $this->apply($text, ['blue']);
    }

    /**
     * Apply cyan color to text
     *
     * @param string $text Text to colorize
     * @return string Cyan text
     */
    public function cyan(string $text): string
    {
        return $this->apply($text, ['cyan']);
    }

    /**
     * Apply magenta color to text
     *
     * @param string $text Text to colorize
     * @return string Magenta text
     */
    public function magenta(string $text): string
    {
        return $this->apply($text, ['magenta']);
    }

    /**
     * Apply bold style to text
     *
     * @param string $text Text to style
     * @return string Bold text
     */
    public function bold(string $text): string
    {
        return $this->apply($text, ['bold']);
    }

    /**
     * Apply dim style to text
     *
     * @param string $text Text to style
     * @return string Dim text
     */
    public function dim(string $text): string
    {
        return $this->apply($text, ['dim']);
    }

    /**
     * Apply underline style to text
     *
     * @param string $text Text to style
     * @return string Underlined text
     */
    public function underline(string $text): string
    {
        return $this->apply($text, ['underline']);
    }
}
