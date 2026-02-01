<?php

namespace Framework\Http;

use Framework\Http\Request;
use Framework\Http\Response;

/**
 * Base Controller Class
 *
 * Provides convenient access to Request and Response methods
 * All controllers can extend this class for easier request/response handling
 *
 * Supports both Web and API responses automatically
 */
abstract class Controller
{
    // ==================== REQUEST METHODS ====================

    /**
     * Get all request data
     *
     * @return array
     */
    protected function all(): array
    {
        return Request::all();
    }

    /**
     * Get request input by key
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function input(string $key, $default = null)
    {
        return Request::input($key, $default);
    }

    /**
     * Check if request has a key
     *
     * @param string $key
     * @return bool
     */
    protected function has(string $key): bool
    {
        return Request::has($key);
    }

    /**
     * Get only specified keys from request
     *
     * @param array $keys
     * @return array
     */
    protected function only(array $keys): array
    {
        return Request::only($keys);
    }

    /**
     * Get all except specified keys from request
     *
     * @param array $keys
     * @return array
     */
    protected function except(array $keys): array
    {
        return Request::except($keys);
    }

    /**
     * Validate required fields
     *
     * @param array $fields
     * @return array Returns empty array if valid, or array of missing fields
     */
    protected function validate(array $fields): array
    {
        return Request::validate($fields);
    }

    /**
     * Get uploaded file
     *
     * @param string|null $key
     * @return mixed
     */
    protected function file(?string $key = null)
    {
        return Request::file($key);
    }

    /**
     * Check if request is JSON
     *
     * @return bool
     */
    protected function isJson(): bool
    {
        return Request::isJson();
    }

    /**
     * Check if request is API request
     *
     * @return bool
     */
    protected function isApi(): bool
    {
        return Request::isApi();
    }

    /**
     * Get request method
     *
     * @return string
     */
    protected function method(): string
    {
        return Request::method();
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    protected function ip(): string
    {
        return Request::ip();
    }

    // ==================== RESPONSE METHODS ====================

    /**
     * Return JSON response
     *
     * @param array $data
     * @param int $statusCode
     * @return void
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        Response::json($data, $statusCode);
    }

    /**
     * Return success response
     *
     * @param string $message
     * @param array $data
     * @param int $code
     * @return void
     */
    protected function success(string $message, array $data = [], int $code = 200): void
    {
        Response::success($message, $data, $code);
    }

    /**
     * Return error response
     *
     * @param string $message
     * @param int $code
     * @param array $errors
     * @return void
     */
    protected function error(string $message, int $code = 400, array $errors = []): void
    {
        Response::error($message, $code, $errors);
    }

    /**
     * Return validation error response
     *
     * @param array $errors
     * @param string $message
     * @return void
     */
    protected function validationError(array $errors, string $message = 'Validation failed'): void
    {
        Response::validationError($errors, $message);
    }

    /**
     * Return created response (201)
     *
     * @param array $data
     * @param string $message
     * @return void
     */
    protected function created(array $data = [], string $message = 'Resource created successfully'): void
    {
        Response::created($data, $message);
    }

    /**
     * Return not found response (404)
     *
     * @param string $message
     * @return void
     */
    protected function notFound(string $message = 'Resource not found'): void
    {
        Response::notFound($message);
    }

    /**
     * Return unauthorized response (401)
     *
     * @param string $message
     * @return void
     */
    protected function unauthorized(string $message = 'Unauthorized'): void
    {
        Response::unauthorized($message);
    }

    /**
     * Return forbidden response (403)
     *
     * @param string $message
     * @return void
     */
    protected function forbidden(string $message = 'Forbidden'): void
    {
        Response::forbidden($message);
    }

    /**
     * Render a view (for web endpoints)
     *
     * @param string $view View name using dot notation
     * @param array $data Data to pass to the view
     * @return string
     */
    protected function view(string $view, array $data = []): string
    {
        return view($view, $data);
    }

    /**
     * Render an Inertia component (for web endpoints with React/Vue)
     *
     * @param string $component Component name
     * @param array $props Props to pass to the component
     * @return void
     */
    protected function inertia(string $component, array $props = []): void
    {
        inertia($component, $props);
    }

    /**
     * Redirect to URL
     *
     * @param string $url
     * @param int $statusCode
     * @return void
     */
    protected function redirect(string $url, int $statusCode = 302): void
    {
        Response::redirect($url, $statusCode);
    }

    /**
     * Redirect to named route
     *
     * @param string $name
     * @param array $parameters
     * @param int $statusCode
     * @return void
     */
    protected function redirectToRoute(string $name, array $parameters = [], int $statusCode = 302): void
    {
        Response::redirectToRoute($name, $parameters, $statusCode);
    }

    /**
     * Redirect back to previous page
     *
     * @param string $fallback
     * @return void
     */
    protected function redirectBack(string $fallback = '/'): void
    {
        Response::redirectBack($fallback);
    }

    /**
     * Send file download response
     *
     * @param string $filePath
     * @param string|null $fileName
     * @param array $headers
     * @return void
     */
    protected function download(string $filePath, ?string $fileName = null, array $headers = []): void
    {
        Response::download($filePath, $fileName, $headers);
    }
}
