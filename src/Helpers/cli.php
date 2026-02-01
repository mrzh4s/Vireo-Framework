<?php

/**
 * CLI Helper Functions
 *
 * Global helper functions for CLI operations
 */

if (!function_exists('console')) {
    /**
     * Get Console instance
     *
     * @return \Framework\Cli\Console
     */
    function console(): \Framework\Cli\Console
    {
        return \Framework\Cli\Console::getInstance();
    }
}

if (!function_exists('cli_output')) {
    /**
     * Create a new Output instance
     *
     * @return \Framework\Cli\Output
     */
    function cli_output(): \Framework\Cli\Output
    {
        return new \Framework\Cli\Output();
    }
}

if (!function_exists('cli_color')) {
    /**
     * Apply color to text
     *
     * @param string $text Text to colorize
     * @param string $color Color name (red, green, yellow, blue, cyan, magenta)
     * @return string Colored text
     */
    function cli_color(string $text, string $color): string
    {
        $colorObj = new \Framework\Cli\Color();
        $method = $color;

        if (method_exists($colorObj, $method)) {
            return $colorObj->$method($text);
        }

        return $text;
    }
}

if (!function_exists('cli_ask')) {
    /**
     * Ask a question in CLI
     *
     * @param string $question Question text
     * @param string|null $default Default answer
     * @return string User's answer
     */
    function cli_ask(string $question, ?string $default = null): string
    {
        $output = cli_output();
        $questionObj = new \Framework\Cli\Question($output);
        return $questionObj->ask($question, $default);
    }
}

if (!function_exists('cli_confirm')) {
    /**
     * Ask a confirmation question in CLI
     *
     * @param string $question Question text
     * @param bool $default Default answer
     * @return bool User's confirmation
     */
    function cli_confirm(string $question, bool $default = false): bool
    {
        $output = cli_output();
        $questionObj = new \Framework\Cli\Question($output);
        return $questionObj->confirm($question, $default);
    }
}

if (!function_exists('cli_choice')) {
    /**
     * Ask a choice question in CLI
     *
     * @param string $question Question text
     * @param array<int|string, string> $choices Available choices
     * @param mixed $default Default choice
     * @return string Selected choice
     */
    function cli_choice(string $question, array $choices, mixed $default = null): string
    {
        $output = cli_output();
        $questionObj = new \Framework\Cli\Question($output);
        return $questionObj->choice($question, $choices, $default);
    }
}

if (!function_exists('cli_info')) {
    /**
     * Output info message in CLI
     *
     * @param string $message Message text
     * @return void
     */
    function cli_info(string $message): void
    {
        $output = cli_output();
        $output->info($message);
    }
}

if (!function_exists('cli_success')) {
    /**
     * Output success message in CLI
     *
     * @param string $message Message text
     * @return void
     */
    function cli_success(string $message): void
    {
        $output = cli_output();
        $output->success($message);
    }
}

if (!function_exists('cli_warning')) {
    /**
     * Output warning message in CLI
     *
     * @param string $message Message text
     * @return void
     */
    function cli_warning(string $message): void
    {
        $output = cli_output();
        $output->warning($message);
    }
}

if (!function_exists('cli_error')) {
    /**
     * Output error message in CLI
     *
     * @param string $message Message text
     * @return void
     */
    function cli_error(string $message): void
    {
        $output = cli_output();
        $output->error($message);
    }
}
