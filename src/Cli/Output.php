<?php

namespace Framework\Cli;

/**
 * Output - Console output formatter with color support
 *
 * Handles all console output with formatting, colors, and verbosity levels.
 * Provides convenience methods for common output types (info, success, error, warning).
 */
class Output
{
    /**
     * Verbosity levels
     */
    public const VERBOSITY_QUIET = 0;
    public const VERBOSITY_NORMAL = 1;
    public const VERBOSITY_VERBOSE = 2;

    /**
     * Color manager
     */
    private Color $color;

    /**
     * Current verbosity level
     */
    private int $verbosity = self::VERBOSITY_NORMAL;

    /**
     * Create Output instance
     *
     * @param int $verbosity Initial verbosity level
     */
    public function __construct(int $verbosity = self::VERBOSITY_NORMAL)
    {
        $this->color = new Color();
        $this->verbosity = $verbosity;
    }

    /**
     * Write output
     *
     * @param string $message Message to write
     * @param bool $newline Append newline (default: true)
     * @return void
     */
    public function write(string $message, bool $newline = true): void
    {
        if ($this->verbosity === self::VERBOSITY_QUIET) {
            return;
        }

        if ($newline) {
            echo $message . PHP_EOL;
        } else {
            echo $message;
        }
    }

    /**
     * Write output with newline
     *
     * @param string $message Message to write
     * @return void
     */
    public function writeln(string $message): void
    {
        $this->write($message, true);
    }

    /**
     * Write a plain line
     *
     * @param string $message Message to write
     * @return void
     */
    public function line(string $message): void
    {
        $this->writeln($message);
    }

    /**
     * Write info message (blue)
     *
     * @param string $message Message to write
     * @return void
     */
    public function info(string $message): void
    {
        $formatted = $this->color->blue('[INFO] ') . $message;
        $this->writeln($formatted);
    }

    /**
     * Write success message (green)
     *
     * @param string $message Message to write
     * @return void
     */
    public function success(string $message): void
    {
        $formatted = $this->color->green('[SUCCESS] ') . $message;
        $this->writeln($formatted);
    }

    /**
     * Write warning message (yellow)
     *
     * @param string $message Message to write
     * @return void
     */
    public function warning(string $message): void
    {
        $formatted = $this->color->yellow('[WARNING] ') . $message;
        $this->writeln($formatted);
    }

    /**
     * Write error message (red)
     *
     * @param string $message Message to write
     * @return void
     */
    public function error(string $message): void
    {
        $formatted = $this->color->red('[ERROR] ') . $message;
        fwrite(STDERR, $formatted . PHP_EOL);
    }

    /**
     * Write empty line(s)
     *
     * @param int $count Number of newlines
     * @return void
     */
    public function newLine(int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->writeln('');
        }
    }

    /**
     * Write a block of text with border
     *
     * @param array<string> $lines Lines to write
     * @param string $type Block type: 'info', 'success', 'warning', 'error'
     * @return void
     */
    public function block(array $lines, string $type = 'info'): void
    {
        if (empty($lines)) {
            return;
        }

        // Find the longest line
        $maxLength = max(array_map('strlen', $lines));

        // Create border
        $border = str_repeat('=', $maxLength + 4);

        // Apply color based on type
        $colorMethod = match ($type) {
            'success' => 'green',
            'warning' => 'yellow',
            'error' => 'red',
            default => 'blue',
        };

        $this->writeln($this->color->$colorMethod($border));

        foreach ($lines as $line) {
            $padding = str_repeat(' ', $maxLength - strlen($line));
            $this->writeln($this->color->$colorMethod('  ' . $line . $padding . '  '));
        }

        $this->writeln($this->color->$colorMethod($border));
    }

    /**
     * Set verbosity level
     *
     * @param int $level Verbosity level
     * @return void
     */
    public function setVerbosity(int $level): void
    {
        $this->verbosity = $level;
    }

    /**
     * Get verbosity level
     *
     * @return int Current verbosity level
     */
    public function getVerbosity(): int
    {
        return $this->verbosity;
    }

    /**
     * Check if output is verbose
     *
     * @return bool True if verbose
     */
    public function isVerbose(): bool
    {
        return $this->verbosity >= self::VERBOSITY_VERBOSE;
    }

    /**
     * Check if output is quiet
     *
     * @return bool True if quiet
     */
    public function isQuiet(): bool
    {
        return $this->verbosity === self::VERBOSITY_QUIET;
    }

    /**
     * Get the color instance
     *
     * @return Color Color instance
     */
    public function getColor(): Color
    {
        return $this->color;
    }
}
