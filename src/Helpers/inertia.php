<?php
/**
 * Inertia Helper Functions
 * File: apps/core/helpers/inertia.php
 *
 * Global helper functions for Inertia.js integration
 */
use Vireo\Framework\View\Inertia;

if (!function_exists('inertia')) {
    /**
     * Create an Inertia response
     *
     * @param string $component Component name
     * @param array $props Component props
     * @return void
     */
    function inertia($component, $props = []) {
        return Inertia::render($component, $props);
    }
}

if (!function_exists('inertia_location')) {
    /**
     * Redirect to external URL (Inertia-aware)
     *
     * @param string $url
     * @return void
     */
    function inertia_location($url) {
        return Inertia::location($url);
    }
}

if (!function_exists('inertia_lazy')) {
    /**
     * Create a lazy prop (only loaded on partial reload)
     *
     * @param callable $callback
     * @return array
     */
    function inertia_lazy($callback) {
        return Inertia::lazy($callback);
    }
}

if (!function_exists('inertia_flash')) {
    /**
     * Flash a message to the next Inertia response
     *
     * Usage:
     * inertia_flash('success', 'User created successfully!')
     * inertia_flash('error', 'Something went wrong')
     *
     * @param string $key Flash key (success, error, warning, info)
     * @param string $message Message content
     * @return void
     */
    function inertia_flash($key, $message) {
        return Inertia::flash($key, $message);
    }
}

if (!function_exists('inertia_errors')) {
    /**
     * Flash validation errors to the next Inertia response
     *
     * Usage:
     * inertia_errors(['email' => 'Email is required', 'password' => 'Password is required'])
     *
     * @param array $errors Validation errors
     * @return void
     */
    function inertia_errors($errors) {
        return Inertia::flashErrors($errors);
    }
}

if (!function_exists('inertia_old')) {
    /**
     * Flash old input to the next Inertia response
     * Used for form repopulation after validation errors
     *
     * Usage:
     * inertia_old($_POST)
     *
     * @param array $input Old input data
     * @return void
     */
    function inertia_old($input) {
        return Inertia::flashOld($input);
    }
}
