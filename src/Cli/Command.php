<?php

namespace Vireo\Framework\Cli;

/**
 * Command - Abstract base class for all CLI commands
 *
 * Provides common functionality and helper methods for commands.
 * All commands must extend this class and implement the handle() method.
 */
abstract class Command
{
    /**
     * Exit codes
     */
    public const SUCCESS = 0;
    public const FAILURE = 1;
    public const INVALID = 2;

    /**
     * Command signature
     * Example: 'serve {--host=localhost} {--port=8000}'
     */
    protected string $signature = '';

    /**
     * Command description
     */
    protected string $description = '';

    /**
     * Input instance
     */
    protected Input $input;

    /**
     * Output instance
     */
    protected Output $output;

    /**
     * Question instance
     */
    protected Question $question;

    /**
     * Parsed signature arguments
     *
     * @var array<int, string>
     */
    private array $signatureArguments = [];

    /**
     * Parsed signature options
     *
     * @var array<string, mixed>
     */
    private array $signatureOptions = [];

    /**
     * Set input instance
     *
     * @param Input $input Input instance
     * @return void
     */
    public function setInput(Input $input): void
    {
        $this->input = $input;
    }

    /**
     * Set output instance
     *
     * @param Output $output Output instance
     * @return void
     */
    public function setOutput(Output $output): void
    {
        $this->output = $output;
    }

    /**
     * Set question instance
     *
     * @param Question $question Question instance
     * @return void
     */
    public function setQuestion(Question $question): void
    {
        $this->question = $question;
    }

    /**
     * Get command signature
     *
     * @return string Signature
     */
    public function getSignature(): string
    {
        return $this->signature;
    }

    /**
     * Get command description
     *
     * @return string Description
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Parse signature and extract arguments/options
     *
     * @return void
     */
    public function parseSignature(): void
    {
        if (empty($this->signature)) {
            return;
        }

        // Extract arguments: {name}, {name?}, {name=default}
        preg_match_all('/\{([^}]+)\}/', $this->signature, $matches);

        foreach ($matches[1] as $match) {
            if (str_starts_with($match, '--')) {
                // Option
                $optionName = ltrim($match, '-');
                $optionName = rtrim($optionName, '=');
                $this->signatureOptions[$optionName] = true;
            } elseif (str_starts_with($match, '@')) {
                // Role modifier (special case)
                // Extract the parameter name from @{param=default}
                $paramName = trim($match, '@');
                if (str_contains($paramName, '=')) {
                    [$paramName, ] = explode('=', $paramName, 2);
                }
                $this->signatureOptions[$paramName] = true;
            } else {
                // Argument
                $argName = rtrim($match, '?');
                $argName = explode('=', $argName)[0];
                $this->signatureArguments[] = $argName;
            }
        }
    }

    /**
     * Execute the command
     *
     * @return int Exit code
     */
    abstract public function handle(): int;

    /**
     * Run the command
     *
     * @return int Exit code
     */
    public function run(): int
    {
        $this->parseSignature();
        return $this->handle();
    }

    /**
     * Get a positional argument by name
     *
     * @param string $name Argument name
     * @return mixed Argument value or null
     */
    protected function argument(string $name): mixed
    {
        return $this->input->getArgument($name, $this->signatureArguments);
    }

    /**
     * Get an option by name
     *
     * @param string $name Option name
     * @param mixed $default Default value
     * @return mixed Option value or default
     */
    protected function option(string $name, mixed $default = null): mixed
    {
        return $this->input->getOption($name, $default);
    }

    /**
     * Ask a question
     *
     * @param string $question Question text
     * @param string|null $default Default answer
     * @return string User's answer
     */
    protected function ask(string $question, ?string $default = null): string
    {
        return $this->question->ask($question, $default);
    }

    /**
     * Ask a confirmation question
     *
     * @param string $question Question text
     * @param bool $default Default answer
     * @return bool User's confirmation
     */
    protected function confirm(string $question, bool $default = false): bool
    {
        return $this->question->confirm($question, $default);
    }

    /**
     * Ask a choice question
     *
     * @param string $question Question text
     * @param array<int|string, string> $choices Available choices
     * @param mixed $default Default choice
     * @return string Selected choice
     */
    protected function choice(string $question, array $choices, mixed $default = null): string
    {
        return $this->question->choice($question, $choices, $default);
    }

    /**
     * Ask for secret input
     *
     * @param string $question Question text
     * @return string User's secret input
     */
    protected function secret(string $question): string
    {
        return $this->question->secret($question);
    }

    /**
     * Write info message
     *
     * @param string $message Message text
     * @return void
     */
    protected function info(string $message): void
    {
        $this->output->info($message);
    }

    /**
     * Write success message
     *
     * @param string $message Message text
     * @return void
     */
    protected function success(string $message): void
    {
        $this->output->success($message);
    }

    /**
     * Write warning message
     *
     * @param string $message Message text
     * @return void
     */
    protected function warning(string $message): void
    {
        $this->output->warning($message);
    }

    /**
     * Write error message
     *
     * @param string $message Message text
     * @return void
     */
    protected function error(string $message): void
    {
        $this->output->error($message);
    }

    /**
     * Write plain line
     *
     * @param string $message Message text
     * @return void
     */
    protected function line(string $message): void
    {
        $this->output->line($message);
    }

    /**
     * Write empty line
     *
     * @param int $count Number of lines
     * @return void
     */
    protected function newLine(int $count = 1): void
    {
        $this->output->newLine($count);
    }

    /**
     * Display a table
     *
     * @param array<string> $headers Table headers
     * @param array<array<string>> $rows Table rows
     * @return void
     */
    protected function table(array $headers, array $rows): void
    {
        $table = new Table($this->output);
        $table->render($headers, $rows);
    }

    /**
     * Create a progress bar
     *
     * @param int $max Maximum steps
     * @return ProgressBar Progress bar instance
     */
    protected function progressBar(int $max): ProgressBar
    {
        return new ProgressBar($this->output, $max);
    }
}
