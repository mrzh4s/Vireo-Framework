<?php

namespace Vireo\Framework\Http;

/**
 * Request Class
 * Provides static methods for accessing HTTP request data
 * Works seamlessly with Router's internal request parsing
 */
class Request
{
    /**
     * Get all request data
     *
     * @return array
     */
    public static function all()
    {
        return Router::getRequestData();
    }

    /**
     * Get request data by key with optional default
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public static function get($key = null, $default = null)
    {
        return Router::getRequestData($key, $default);
    }

    /**
     * Get input value (alias for get)
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function input($key, $default = null)
    {
        return self::get($key, $default);
    }

    /**
     * Check if request has a key
     *
     * @param string $key
     * @return bool
     */
    public static function has($key)
    {
        $data = self::all();
        return isset($data[$key]);
    }

    /**
     * Check if request has all given keys
     *
     * @param array $keys
     * @return bool
     */
    public static function hasAll(array $keys)
    {
        $data = self::all();
        foreach ($keys as $key) {
            if (!isset($data[$key])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if request has any of the given keys
     *
     * @param array $keys
     * @return bool
     */
    public static function hasAny(array $keys)
    {
        $data = self::all();
        foreach ($keys as $key) {
            if (isset($data[$key])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get only specified keys from request
     *
     * @param array $keys
     * @return array
     */
    public static function only(array $keys)
    {
        $data = self::all();
        $result = [];
        foreach ($keys as $key) {
            if (isset($data[$key])) {
                $result[$key] = $data[$key];
            }
        }
        return $result;
    }

    /**
     * Get all except specified keys from request
     *
     * @param array $keys
     * @return array
     */
    public static function except(array $keys)
    {
        $data = self::all();
        foreach ($keys as $key) {
            unset($data[$key]);
        }
        return $data;
    }

    /**
     * Get uploaded files
     *
     * @param string|null $key
     * @return mixed
     */
    public static function file($key = null)
    {
        return Router::getFiles($key);
    }

    /**
     * Get all uploaded files
     *
     * @return array
     */
    public static function files()
    {
        return Router::getFiles();
    }

    /**
     * Check if request has files
     *
     * @return bool
     */
    public static function hasFile($key = null)
    {
        if ($key === null) {
            return Router::hasFiles();
        }
        $file = Router::getFiles($key);
        return $file !== null;
    }

    /**
     * Get request headers
     *
     * @param string|null $key
     * @return mixed
     */
    public static function header($key = null)
    {
        return Router::getHeaders($key);
    }

    /**
     * Get all request headers
     *
     * @return array
     */
    public static function headers()
    {
        return Router::getHeaders();
    }

    /**
     * Get request method (GET, POST, PUT, DELETE, etc.)
     *
     * @return string
     */
    public static function method()
    {
        return Router::getMethod();
    }

    /**
     * Check if request method matches
     *
     * @param string $method
     * @return bool
     */
    public static function isMethod($method)
    {
        return strtoupper($method) === self::method();
    }

    /**
     * Check if request is GET
     *
     * @return bool
     */
    public static function isGet()
    {
        return self::method() === 'GET';
    }

    /**
     * Check if request is POST
     *
     * @return bool
     */
    public static function isPost()
    {
        return self::method() === 'POST';
    }

    /**
     * Check if request is PUT
     *
     * @return bool
     */
    public static function isPut()
    {
        return self::method() === 'PUT';
    }

    /**
     * Check if request is DELETE
     *
     * @return bool
     */
    public static function isDelete()
    {
        return self::method() === 'DELETE';
    }

    /**
     * Check if request is PATCH
     *
     * @return bool
     */
    public static function isPatch()
    {
        return self::method() === 'PATCH';
    }

    /**
     * Get content type
     *
     * @return string
     */
    public static function contentType()
    {
        return Router::getContentType();
    }

    /**
     * Check if request is JSON
     *
     * @return bool
     */
    public static function isJson()
    {
        return Router::isJson();
    }

    /**
     * Check if request is API request
     *
     * @return bool
     */
    public static function isApi()
    {
        return Router::isApiRequest();
    }

    /**
     * Check if request expects JSON response
     *
     * @return bool
     */
    public static function expectsJson()
    {
        return self::isJson() || self::isApi();
    }

    /**
     * Get request URI
     *
     * @return string
     */
    public static function uri()
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    /**
     * Get request path (without query string)
     *
     * @return string
     */
    public static function path()
    {
        return parse_url(self::uri(), PHP_URL_PATH);
    }

    /**
     * Get full URL
     *
     * @return string
     */
    public static function url()
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = self::uri();
        return "{$protocol}://{$host}{$uri}";
    }

    /**
     * Get query string
     *
     * @return string
     */
    public static function queryString()
    {
        return $_SERVER['QUERY_STRING'] ?? '';
    }

    /**
     * Get IP address
     *
     * @return string
     */
    public static function ip()
    {
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get user agent
     *
     * @return string
     */
    public static function userAgent()
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Check if request is AJAX
     *
     * @return bool
     */
    public static function isAjax()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Check if request is secure (HTTPS)
     *
     * @return bool
     */
    public static function isSecure()
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    }

    /**
     * Get bearer token from Authorization header
     *
     * @return string|null
     */
    public static function bearerToken()
    {
        $header = self::header('Authorization');
        if ($header && strpos($header, 'Bearer ') === 0) {
            return substr($header, 7);
        }
        return null;
    }

    /**
     * Validate required fields (backward compatible - simple validation)
     * Returns array of missing fields or empty array if all present
     *
     * @param array $fields
     * @return array
     */
    public static function validate(array $fields)
    {
        $missing = [];
        $data = self::all();
        foreach ($fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missing[] = $field;
            }
        }
        return $missing;
    }

    /**
     * Validate request data with comprehensive rules
     * Throws ValidationException on failure
     *
     * Usage:
     *   $validated = Request::validateWith([
     *       'email' => 'required|email',
     *       'age' => 'numeric|min:18'
     *   ]);
     *
     * @param array $rules Validation rules
     * @param array $messages Custom error messages (optional)
     * @return array Validated data
     * @throws \Vireo\Framework\Validation\ValidationException
     */
    public static function validateWith(array $rules, array $messages = [])
    {
        $data = self::all();
        return \Vireo\Framework\Validation\Validator::getInstance()->validate($data, $rules, $messages);
    }

    /**
     * Create validator instance without throwing exception
     * Allows checking validation status and errors manually
     *
     * Usage:
     *   $validator = Request::validator(['email' => 'required|email']);
     *   if ($validator->fails()) {
     *       $errors = $validator->errors()->toArray();
     *   }
     *
     * @param array $rules Validation rules
     * @param array $messages Custom error messages (optional)
     * @return \Vireo\Framework\Validation\Validator
     */
    public static function validator(array $rules, array $messages = [])
    {
        $data = self::all();
        return \Vireo\Framework\Validation\Validator::getInstance()->make($data, $rules, $messages);
    }

    /**
     * Validate and sanitize request data in one call
     *
     * Usage:
     *   $validated = Request::validateAndSanitize(
     *       ['email' => 'required|email'],
     *       ['email' => 'email', 'name' => 'string']
     *   );
     *
     * @param array $validationRules Validation rules
     * @param array $sanitizeRules Sanitization rules (optional)
     * @param array $messages Custom validation messages (optional)
     * @return array Validated and sanitized data
     * @throws \Vireo\Framework\Validation\ValidationException
     */
    public static function validateAndSanitize(array $validationRules, array $sanitizeRules = [], array $messages = [])
    {
        $data = self::all();

        // Sanitize first
        if (!empty($sanitizeRules)) {
            $data = \Vireo\Framework\Validation\Sanitizer::getInstance()->sanitize($data, $sanitizeRules);
        }

        // Then validate
        return \Vireo\Framework\Validation\Validator::getInstance()->validate($data, $validationRules, $messages);
    }
}
