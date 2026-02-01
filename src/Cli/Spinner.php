<?php

namespace Vireo\Framework\Cli;

/**
 * Spinner - Loading animation for indefinite operations
 *
 * Displays a spinning animation during operations where
 * progress cannot be measured.
 */
class Spinner
{
    /**
     * Output instance
     */
    private Output $output;

    /**
     * Spinner frames
     *
     * @var array<string>
     */
    private array $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

    /**
     * Current frame index
     */
    private int $currentFrame = 0;

    /**
     * Message to display
     */
    private string $message = '';

    /**
     * Whether spinner is running
     */
    private bool $running = false;

    /**
     * Create Spinner instance
     *
     * @param Output $output Output instance
     * @param string $message Optional message
     */
    public function __construct(Output $output, string $message = '')
    {
        $this->output = $output;
        $this->message = $message;
    }

    /**
     * Start the spinner
     *
     * @param string|null $message Optional message
     * @return void
     */
    public function start(?string $message = null): void
    {
        if ($message !== null) {
            $this->message = $message;
        }

        $this->running = true;
        $this->currentFrame = 0;
        $this->tick();
    }

    /**
     * Update spinner (single tick)
     *
     * @return void
     */
    public function tick(): void
    {
        if (!$this->running) {
            return;
        }

        $frame = $this->frames[$this->currentFrame];
        $output = "\r" . $this->output->getColor()->cyan($frame) . ' ' . $this->message;

        echo $output;

        $this->currentFrame = ($this->currentFrame + 1) % count($this->frames);
    }

    /**
     * Stop the spinner with success
     *
     * @param string|null $message Optional completion message
     * @return void
     */
    public function stop(?string $message = null): void
    {
        if (!$this->running) {
            return;
        }

        $this->running = false;

        $checkmark = $this->output->getColor()->green('✓');
        $finalMessage = $message ?? $this->message;

        echo "\r" . $checkmark . ' ' . $finalMessage . PHP_EOL;
    }

    /**
     * Stop the spinner with failure
     *
     * @param string|null $message Optional error message
     * @return void
     */
    public function fail(?string $message = null): void
    {
        if (!$this->running) {
            return;
        }

        $this->running = false;

        $cross = $this->output->getColor()->red('✗');
        $finalMessage = $message ?? $this->message;

        echo "\r" . $cross . ' ' . $finalMessage . PHP_EOL;
    }

    /**
     * Update the spinner message
     *
     * @param string $message New message
     * @return void
     */
    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    /**
     * Set spinner frames
     *
     * @param array<string> $frames Frame characters
     * @return self
     */
    public function setFrames(array $frames): self
    {
        if (!empty($frames)) {
            $this->frames = $frames;
            $this->currentFrame = 0;
        }

        return $this;
    }

    /**
     * Check if spinner is running
     *
     * @return bool True if running
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Spin for a callback function
     *
     * Displays spinner while callback executes,
     * then stops with success or failure message.
     *
     * @param callable $callback Function to execute
     * @param string|null $successMessage Success message
     * @param string|null $errorMessage Error message
     * @return mixed Return value from callback
     * @throws \Exception If callback throws
     */
    public function spinWhile(
        callable $callback,
        ?string $successMessage = null,
        ?string $errorMessage = null
    ): mixed {
        $this->start();

        try {
            $result = $callback($this);
            $this->stop($successMessage);
            return $result;
        } catch (\Exception $e) {
            $this->fail($errorMessage ?? $e->getMessage());
            throw $e;
        }
    }
}
