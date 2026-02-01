<?php

namespace Vireo\Framework\Cli\Commands;

use Vireo\Framework\Cli\Command;

/**
 * TestCliCommand - Test CLI features
 *
 * Tests and demonstrates various CLI features like colors, tables,
 * progress bars, spinners, and interactive prompts.
 */
class TestCliCommand extends Command
{
    /**
     * Command signature
     */
    protected string $signature = 'test:cli {--interactive} {--progress} {--table} {--colors}';

    /**
     * Command description
     */
    protected string $description = 'Test and demonstrate CLI features';

    /**
     * Execute the command
     *
     * @return int Exit code
     */
    public function handle(): int
    {
        $this->info('Vireo Framework CLI Test');
        $this->newLine();

        // Test colors if requested or no specific test
        if ($this->option('colors') || !$this->hasSpecificTest()) {
            $this->testColors();
        }

        // Test table if requested or no specific test
        if ($this->option('table') || !$this->hasSpecificTest()) {
            $this->testTable();
        }

        // Test progress bar if requested or no specific test
        if ($this->option('progress') || !$this->hasSpecificTest()) {
            $this->testProgressBar();
        }

        // Test interactive features if requested
        if ($this->option('interactive')) {
            $this->testInteractive();
        }

        $this->newLine();
        $this->success('CLI test completed!');

        return self::SUCCESS;
    }

    /**
     * Check if a specific test was requested
     *
     * @return bool
     */
    private function hasSpecificTest(): bool
    {
        return $this->option('colors')
            || $this->option('table')
            || $this->option('progress')
            || $this->option('interactive');
    }

    /**
     * Test color output
     *
     * @return void
     */
    private function testColors(): void
    {
        $this->line($this->output->getColor()->bold('Testing Colors:'));
        $this->newLine();

        $this->info('This is an info message (blue)');
        $this->success('This is a success message (green)');
        $this->warning('This is a warning message (yellow)');
        $this->error('This is an error message (red)');

        $this->newLine();
        $this->line('Color methods: ' . $this->output->getColor()->red('red') . ' '
            . $this->output->getColor()->green('green') . ' '
            . $this->output->getColor()->yellow('yellow') . ' '
            . $this->output->getColor()->blue('blue') . ' '
            . $this->output->getColor()->cyan('cyan') . ' '
            . $this->output->getColor()->magenta('magenta'));

        $this->newLine();
        $this->line('Text styles: '
            . $this->output->getColor()->bold('bold') . ' '
            . $this->output->getColor()->dim('dim') . ' '
            . $this->output->getColor()->underline('underline'));

        $this->newLine();
    }

    /**
     * Test table formatting
     *
     * @return void
     */
    private function testTable(): void
    {
        $this->line($this->output->getColor()->bold('Testing Tables:'));
        $this->newLine();

        $headers = ['Name', 'Email', 'Role', 'Status'];
        $rows = [
            ['John Doe', 'john@example.com', 'Admin', 'Active'],
            ['Jane Smith', 'jane@example.com', 'User', 'Active'],
            ['Bob Johnson', 'bob@example.com', 'Moderator', 'Inactive'],
        ];

        $this->table($headers, $rows);
        $this->newLine();
    }

    /**
     * Test progress bar
     *
     * @return void
     */
    private function testProgressBar(): void
    {
        $this->line($this->output->getColor()->bold('Testing Progress Bar:'));
        $this->newLine();

        $progress = $this->progressBar(100);
        $progress->start();

        for ($i = 0; $i <= 100; $i++) {
            $progress->setCurrent($i);
            usleep(20000); // 20ms delay
        }

        $progress->finish();
        $this->newLine();
    }

    /**
     * Test interactive features
     *
     * @return void
     */
    private function testInteractive(): void
    {
        $this->line($this->output->getColor()->bold('Testing Interactive Features:'));
        $this->newLine();

        // Test ask
        $name = $this->ask('What is your name?', 'Anonymous');
        $this->success("Hello, {$name}!");
        $this->newLine();

        // Test confirm
        $confirmed = $this->confirm('Do you want to continue?', true);
        if ($confirmed) {
            $this->info('You chose to continue.');
        } else {
            $this->warning('You chose not to continue.');
        }
        $this->newLine();

        // Test choice
        $choice = $this->choice(
            'Select your favorite color:',
            ['Red', 'Green', 'Blue', 'Yellow'],
            'Blue'
        );
        $this->success("You selected: {$choice}");
        $this->newLine();
    }
}
