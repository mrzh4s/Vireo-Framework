<?php

namespace Framework\Cli;

/**
 * ProgressBar - Progress indicator for long-running operations
 *
 * Displays a progress bar with percentage, current/max values,
 * and estimated time remaining.
 */
class ProgressBar
{
    /**
     * Output instance
     */
    private Output $output;

    /**
     * Maximum steps
     */
    private int $max;

    /**
     * Current progress
     */
    private int $current = 0;

    /**
     * Progress bar width
     */
    private int $barWidth = 50;

    /**
     * Start time
     */
    private float $startTime;

    /**
     * Whether progress bar has started
     */
    private bool $started = false;

    /**
     * Create ProgressBar instance
     *
     * @param Output $output Output instance
     * @param int $max Maximum steps
     */
    public function __construct(Output $output, int $max = 100)
    {
        $this->output = $output;
        $this->max = max(1, $max);
    }

    /**
     * Start the progress bar
     *
     * @return void
     */
    public function start(): void
    {
        $this->startTime = microtime(true);
        $this->current = 0;
        $this->started = true;
        $this->render();
    }

    /**
     * Advance the progress bar
     *
     * @param int $step Steps to advance
     * @return void
     */
    public function advance(int $step = 1): void
    {
        if (!$this->started) {
            $this->start();
        }

        $this->current = min($this->max, $this->current + $step);
        $this->render();
    }

    /**
     * Set current progress
     *
     * @param int $current Current value
     * @return void
     */
    public function setCurrent(int $current): void
    {
        if (!$this->started) {
            $this->start();
        }

        $this->current = min($this->max, max(0, $current));
        $this->render();
    }

    /**
     * Finish the progress bar
     *
     * @return void
     */
    public function finish(): void
    {
        $this->current = $this->max;
        $this->render();
        echo PHP_EOL;
        $this->started = false;
    }

    /**
     * Render the progress bar
     *
     * @return void
     */
    private function render(): void
    {
        $percentage = $this->max > 0 ? ($this->current / $this->max) * 100 : 0;
        $filledWidth = (int)round(($this->barWidth * $this->current) / $this->max);
        $emptyWidth = $this->barWidth - $filledWidth;

        // Build progress bar
        $bar = '[';
        $bar .= $this->output->getColor()->green(str_repeat('=', $filledWidth));

        if ($filledWidth < $this->barWidth && $this->current < $this->max) {
            $bar .= '>';
            $emptyWidth--;
        }

        $bar .= str_repeat(' ', max(0, $emptyWidth));
        $bar .= ']';

        // Calculate elapsed time and ETA
        $elapsed = microtime(true) - $this->startTime;
        $eta = $this->current > 0 ? (($elapsed / $this->current) * ($this->max - $this->current)) : 0;

        // Format output
        $output = sprintf(
            "\r%s %3d%% (%d/%d) [%s remaining]",
            $bar,
            (int)$percentage,
            $this->current,
            $this->max,
            $this->formatTime($eta)
        );

        echo $output;
    }

    /**
     * Format time in human-readable format
     *
     * @param float $seconds Seconds
     * @return string Formatted time
     */
    private function formatTime(float $seconds): string
    {
        if ($seconds < 1) {
            return '< 1 sec';
        }

        if ($seconds < 60) {
            return sprintf('%d secs', (int)$seconds);
        }

        if ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $secs = $seconds % 60;
            return sprintf('%d min %d sec', $minutes, $secs);
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return sprintf('%d hr %d min', $hours, $minutes);
    }

    /**
     * Set progress bar width
     *
     * @param int $width Bar width
     * @return self
     */
    public function setBarWidth(int $width): self
    {
        $this->barWidth = max(10, $width);
        return $this;
    }

    /**
     * Get current progress
     *
     * @return int Current value
     */
    public function getCurrent(): int
    {
        return $this->current;
    }

    /**
     * Get maximum value
     *
     * @return int Max value
     */
    public function getMax(): int
    {
        return $this->max;
    }

    /**
     * Get percentage complete
     *
     * @return float Percentage (0-100)
     */
    public function getPercentage(): float
    {
        return $this->max > 0 ? ($this->current / $this->max) * 100 : 0;
    }
}
