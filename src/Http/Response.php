<?php

namespace Framework\Http;

/**
 * Response Class
 * Provides static methods for HTTP responses
 * Works seamlessly with your framework's routing and helper system
 */
class Response
{
    /**
     * Send JSON response
     *
     * @param array $data
     * @param int $statusCode
     * @return void
     */
    public static function json($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');

        // Extract message (if exists)
        $message = '';
        if (is_array($data) && isset($data['message'])) {
            $message = $data['message'];
            unset($data['message']);
        }

        // Base response
        $response = [
            'status' => self::getHttpStatusName($statusCode),
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
            'server_time' => time(),
        ];

        if (isset($data['data'])) {
            $response['data'] = $data['data'];
        } elseif (!empty($data)) {
            $response['data'] = $data;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Send JSON success response
     *
     * @param string $message
     * @param array $data
     * @param int $code
     * @return void
     */
    public static function success($message, $data = [], $code = 200)
    {
        self::json([
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * Send JSON error response
     *
     * @param string $message
     * @param int $code
     * @param array $errors
     * @return void
     */
    public static function error($message, $code = 400, $errors = [])
    {
        $response = ['message' => $message];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        self::json($response, $code);
    }

    /**
     * Send validation error response
     *
     * @param array $errors
     * @param string $message
     * @return void
     */
    public static function validationError($errors, $message = 'Validation failed')
    {
        self::json([
            'message' => $message,
            'errors' => $errors
        ], 422);
    }

    /**
     * Send 404 not found response
     *
     * @param string $message
     * @return void
     */
    public static function notFound($message = 'Resource not found')
    {
        self::error($message, 404);
    }

    /**
     * Send 401 unauthorized response
     *
     * @param string $message
     * @return void
     */
    public static function unauthorized($message = 'Unauthorized')
    {
        self::error($message, 401);
    }

    /**
     * Send 403 forbidden response
     *
     * @param string $message
     * @return void
     */
    public static function forbidden($message = 'Forbidden')
    {
        self::error($message, 403);
    }

    /**
     * Send 500 server error response
     *
     * @param string $message
     * @return void
     */
    public static function serverError($message = 'Internal server error')
    {
        self::error($message, 500);
    }

    /**
     * Redirect to URL
     *
     * @param string $url
     * @param int $statusCode
     * @return void
     */
    public static function redirect($url, $statusCode = 302)
    {
        header("Location: {$url}", true, $statusCode);
        exit;
    }

    /**
     * Redirect to named route
     *
     * @param string $name
     * @param array $parameters
     * @param int $statusCode
     * @return void
     */
    public static function redirectToRoute($name, $parameters = [], $statusCode = 302)
    {
        $url = Router::url($name, $parameters);
        self::redirect($url, $statusCode);
    }

    /**
     * Redirect back to previous page
     *
     * @param string $fallback
     * @return void
     */
    public static function redirectBack($fallback = '/')
    {
        $referrer = $_SERVER['HTTP_REFERER'] ?? $fallback;
        self::redirect($referrer);
    }

    /**
     * Send HTML response
     *
     * @param string $html
     * @param int $statusCode
     * @return void
     */
    public static function html($html, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit;
    }

    /**
     * Send plain text response
     *
     * @param string $text
     * @param int $statusCode
     * @return void
     */
    public static function text($text, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: text/plain; charset=UTF-8');
        echo $text;
        exit;
    }

    /**
     * Send XML response
     *
     * @param string $xml
     * @param int $statusCode
     * @return void
     */
    public static function xml($xml, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/xml; charset=UTF-8');
        echo $xml;
        exit;
    }

    /**
     * Send file download response
     *
     * @param string $filePath
     * @param string|null $fileName
     * @param array $headers
     * @return void
     */
    public static function download($filePath, $fileName = null, $headers = [])
    {
        if (!file_exists($filePath)) {
            self::notFound('File not found');
        }

        $fileName = $fileName ?? basename($filePath);
        $fileSize = filesize($filePath);
        $mimeType = mime_content_type($filePath);

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        foreach ($headers as $key => $value) {
            header("{$key}: {$value}");
        }

        readfile($filePath);
        exit;
    }

    /**
     * Send file inline response (view in browser)
     *
     * @param string $filePath
     * @param string|null $fileName
     * @return void
     */
    public static function file($filePath, $fileName = null)
    {
        if (!file_exists($filePath)) {
            self::notFound('File not found');
        }

        $fileName = $fileName ?? basename($filePath);
        $fileSize = filesize($filePath);
        $mimeType = mime_content_type($filePath);

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . $fileName . '"');
        header('Content-Length: ' . $fileSize);

        readfile($filePath);
        exit;
    }

    /**
     * Set response header
     *
     * @param string $key
     * @param string $value
     * @return void
     */
    public static function header($key, $value)
    {
        header("{$key}: {$value}");
    }

    /**
     * Set multiple response headers
     *
     * @param array $headers
     * @return void
     */
    public static function headers($headers)
    {
        foreach ($headers as $key => $value) {
            self::header($key, $value);
        }
    }

    /**
     * Set response status code
     *
     * @param int $statusCode
     * @return void
     */
    public static function status($statusCode)
    {
        http_response_code($statusCode);
    }

    /**
     * Set cookie
     *
     * @param string $name
     * @param string $value
     * @param int $expires
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httpOnly
     * @return void
     */
    public static function cookie(
        $name,
        $value,
        $expires = 0,
        $path = '/',
        $domain = '',
        $secure = false,
        $httpOnly = true
    ) {
        setcookie($name, $value, $expires, $path, $domain, $secure, $httpOnly);
    }

    /**
     * Delete cookie
     *
     * @param string $name
     * @param string $path
     * @param string $domain
     * @return void
     */
    public static function deleteCookie($name, $path = '/', $domain = '')
    {
        self::cookie($name, '', time() - 3600, $path, $domain);
    }

    /**
     * Send no content response (204)
     *
     * @return void
     */
    public static function noContent()
    {
        http_response_code(204);
        exit;
    }

    /**
     * Send created response (201)
     *
     * @param array $data
     * @param string $message
     * @return void
     */
    public static function created($data = [], $message = 'Resource created successfully')
    {
        self::success($message, $data, 201);
    }

    /**
     * Send accepted response (202)
     *
     * @param string $message
     * @return void
     */
    public static function accepted($message = 'Request accepted')
    {
        self::success($message, [], 202);
    }

    /**
     * Get HTTP status name from code
     *
     * @param int $code
     * @return string
     */
    private static function getHttpStatusName($code)
    {
        if ($code >= 100 && $code < 200) {
            return "Informational";
        } elseif ($code >= 200 && $code < 300) {
            return "Success";
        } elseif ($code >= 300 && $code < 400) {
            return "Redirect";
        } elseif ($code >= 400 && $code < 500) {
            return "Client Error";
        } elseif ($code >= 500 && $code < 600) {
            return "Server Error";
        }
        return "Unknown";
    }

    /**
     * Check if client accepts JSON
     *
     * @return bool
     */
    public static function wantsJson()
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (strpos($accept, 'application/json') !== false) {
            return true;
        }

        if (strpos($_SERVER['REQUEST_URI'], '/api/') === 0) {
            return true;
        }

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        return false;
    }
}
