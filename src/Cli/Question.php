<?php

namespace Vireo\Framework\Cli;

/**
 * Question - Interactive prompt handler
 *
 * Provides interactive CLI features without external dependencies.
 * Uses native PHP functions for reading user input.
 */
class Question
{
    /**
     * Output instance
     */
    private Output $output;

    /**
     * Create Question instance
     *
     * @param Output $output Output instance
     */
    public function __construct(Output $output)
    {
        $this->output = $output;
    }

    /**
     * Ask a question and get text input
     *
     * @param string $question Question text
     * @param string|null $default Default answer
     * @return string User's answer
     */
    public function ask(string $question, ?string $default = null): string
    {
        $defaultText = $default !== null ? " [{$default}]" : '';
        $this->output->write($question . $defaultText . ': ', false);

        $answer = $this->readLine();

        return $answer !== '' ? $answer : ($default ?? '');
    }

    /**
     * Ask a confirmation question (yes/no)
     *
     * @param string $question Question text
     * @param bool $default Default answer
     * @return bool User's confirmation
     */
    public function confirm(string $question, bool $default = false): bool
    {
        $defaultText = $default ? '[Y/n]' : '[y/N]';
        $this->output->write("{$question} {$defaultText}: ", false);

        $answer = $this->readLine();

        if ($answer === '') {
            return $default;
        }

        return in_array(strtolower($answer), ['y', 'yes', '1', 'true'], true);
    }

    /**
     * Ask a choice question (menu)
     *
     * @param string $question Question text
     * @param array<int|string, string> $choices Available choices
     * @param mixed $default Default choice
     * @return string Selected choice
     */
    public function choice(string $question, array $choices, mixed $default = null): string
    {
        $this->output->writeln($question);
        $this->output->newLine();

        $choiceKeys = array_keys($choices);
        foreach ($choices as $index => $choice) {
            $marker = ($default !== null && $default === $choice) ? '*' : ' ';
            $this->output->writeln("  [{$marker}] {$index}. {$choice}");
        }

        $this->output->newLine();
        $defaultText = $default !== null ? " [{$default}]" : '';
        $this->output->write('Select option' . $defaultText . ': ', false);

        $answer = $this->readLine();

        // Return default if empty
        if ($answer === '' && $default !== null) {
            return $default;
        }

        // Support numeric selection
        if (is_numeric($answer) && isset($choices[$answer])) {
            return $choices[$answer];
        }

        // Support text selection
        if (in_array($answer, $choices, true)) {
            return $answer;
        }

        // Support key selection
        foreach ($choices as $key => $value) {
            if ((string)$key === $answer) {
                return $value;
            }
        }

        // Fallback to default or first choice
        return $default ?? reset($choices);
    }

    /**
     * Ask for secret input (password)
     *
     * @param string $question Question text
     * @return string User's secret input
     */
    public function secret(string $question): string
    {
        $this->output->write("{$question}: ", false);

        $password = $this->readHidden();

        $this->output->newLine();
        return $password;
    }

    /**
     * Ask with validation
     *
     * @param string $question Question text
     * @param callable $validator Validation function
     * @param string|null $default Default answer
     * @param int $maxAttempts Maximum attempts
     * @return string Valid answer
     */
    public function askWithValidation(
        string $question,
        callable $validator,
        ?string $default = null,
        int $maxAttempts = 3
    ): string {
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            $answer = $this->ask($question, $default);

            try {
                $result = $validator($answer);
                if ($result === true) {
                    return $answer;
                }

                if (is_string($result)) {
                    $this->output->error($result);
                } else {
                    $this->output->error('Invalid input. Please try again.');
                }
            } catch (\Exception $e) {
                $this->output->error($e->getMessage());
            }

            $attempts++;

            if ($attempts < $maxAttempts) {
                $this->output->warning("Attempt {$attempts} of {$maxAttempts}");
            }
        }

        throw new \RuntimeException('Maximum validation attempts exceeded.');
    }

    /**
     * Read a line from STDIN
     *
     * @return string Input line
     */
    private function readLine(): string
    {
        $handle = fopen('php://stdin', 'r');
        $line = fgets($handle);
        fclose($handle);

        return trim($line);
    }

    /**
     * Read hidden input (for passwords)
     *
     * @return string Hidden input
     */
    private function readHidden(): string
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows - use PowerShell
            $command = 'powershell -Command "$password = Read-Host -AsSecureString; '
                     . '[Runtime.InteropServices.Marshal]::PtrToStringAuto('
                     . '[Runtime.InteropServices.Marshal]::SecureStringToBSTR($password))"';

            $password = shell_exec($command);
            return trim($password ?? '');
        } else {
            // Unix/Linux/Mac - use stty
            $command = "/bin/bash -c 'read -s password && echo \$password'";
            $password = shell_exec($command);
            return trim($password ?? '');
        }
    }
}
