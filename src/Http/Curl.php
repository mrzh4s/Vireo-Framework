<?php

namespace Framework\Http;

use Exception;
use CURLFile;
use InvalidArgumentException;

class Curl {
    private static $instance = null;
    private $defaultTimeout = 30;
    private $defaultUserAgent = 'KITER/1.0';

    private function __construct() {
    }

    /**
     * Singleton pattern
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Extract HTTP headers from $_SERVER
     */
    public function getHeaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$headerName] = $value;
            }
        }
        return $headers;
    }

    /**
     * Make HTTP request with enhanced options
     */
    public function request($url, $data = null, $method = 'POST', $options = []) {
        try {
            // Handle legacy parameters
            if (is_bool($options)) {
                $ssl = $options;
                $contentType = func_get_args()[4] ?? 'json';
                $options = [
                    'ssl_verify' => $ssl,
                    'content_type' => $contentType
                ];
            }

            // Set default options
            $options = array_merge([
                'ssl_verify' => false,
                'content_type' => 'json',
                'timeout' => $this->defaultTimeout,
                'user_agent' => $this->defaultUserAgent,
                'headers' => [],
                'follow_redirects' => true,
                'max_redirects' => 5
            ], $options);

            $headers = $this->buildHeaders($options['content_type'], $options['headers']);
            
            $ch = curl_init();
            $this->setCurlOptions($ch, $url, $data, $method, $headers, $options);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlInfo = curl_getinfo($ch);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                throw new Exception("cURL Error: " . $curlError);
            }

            $result = $this->buildResponse($url, $data, $method, $headers, $response, $httpCode, $curlInfo);
            
            return $result;

        } catch (Exception $e) {
            error_log("Curl request error: " . $e->getMessage());
            return $this->buildErrorResponse($url, $method, $e->getMessage());
        }
    }

    /**
     * Build request headers
     */
    private function buildHeaders($contentType, $customHeaders = []) {
        $headers = [
            "Origin: " . config('app.url', 'http://localhost'),
        ];

        // Add content type header
        switch ($contentType) {
            case 'json':
                $headers[] = "Content-Type: application/json";
                break;
            case 'urlencoded':
                $headers[] = "Content-Type: application/x-www-form-urlencoded";
                break;
            case 'multipart':
                // Don't set content type for multipart, let cURL handle it
                break;
            case 'xml':
                $headers[] = "Content-Type: application/xml";
                break;
            default:
                if ($contentType) {
                    $headers[] = "Content-Type: " . $contentType;
                }
        }

        // Add custom headers
        foreach ($customHeaders as $key => $value) {
            if (is_numeric($key)) {
                $headers[] = $value;
            } else {
                $headers[] = "{$key}: {$value}";
            }
        }

        return $headers;
    }

    /**
     * Set cURL options
     */
    private function setCurlOptions($ch, $url, $data, $method, $headers, $options) {
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => $options['ssl_verify'],
            CURLOPT_SSL_VERIFYHOST => $options['ssl_verify'] ? 2 : 0,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_TIMEOUT => $options['timeout'],
            CURLOPT_USERAGENT => $options['user_agent'],
            CURLOPT_FOLLOWLOCATION => $options['follow_redirects'],
            CURLOPT_MAXREDIRS => $options['max_redirects'],
            CURLOPT_HEADER => false,
            CURLOPT_NOBODY => false,
        ]);

        // Set data for POST/PUT requests
        if ($data !== null && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    }

    /**
     * Build response array
     */
    private function buildResponse($url, $data, $method, $headers, $response, $httpCode, $curlInfo) {
        $parsed = parse_url($url);
        $requestURL = $parsed['scheme'] . '://' . $parsed['host'];
        $appUrl = parse_url(config('app.url', 'http://localhost'))['scheme'] . '://' . parse_url(config('app.url'))['host'];
        
        $traffic = ($appUrl == $requestURL) ? 'inbound' : 'outbound';
        $status = ($httpCode >= 200 && $httpCode < 300) ? 'success' : 'error';

        return [
            'response' => $response,
            'headers' => json_encode($curlInfo),
            'body' => is_string($data) ? $data : json_encode($data),
            'request_method' => strtoupper($method),
            'url' => $url,
            'traffic' => $traffic,
            'status' => $status,
            'http_code' => $httpCode,
            'curl_info' => $curlInfo
        ];
    }

    /**
     * Build error response
     */
    private function buildErrorResponse($url, $method, $error) {
        return [
            'response' => null,
            'headers' => json_encode(['error' => $error]),
            'body' => null,
            'request_method' => strtoupper($method),
            'url' => $url,
            'traffic' => 'outbound',
            'status' => 'error',
            'http_code' => 0,
            'error' => $error
        ];
    }


    /**
     * Make GET request
     */
    public function get($url, $options = []) {
        return $this->request($url, null, 'GET', $options);
    }

    /**
     * Make POST request
     */
    public function post($url, $data, $options = []) {
        return $this->request($url, $data, 'POST', $options);
    }

    /**
     * Make PUT request
     */
    public function put($url, $data, $options = []) {
        return $this->request($url, $data, 'PUT', $options);
    }

    /**
     * Make DELETE request
     */
    public function delete($url, $options = []) {
        return $this->request($url, null, 'DELETE', $options);
    }

    /**
     * Download file
     */
    public function download($url, $filepath, $options = []) {
        try {
            $options = array_merge([
                'timeout' => 300, // 5 minutes for downloads
                'ssl_verify' => false
            ], $options);

            $fp = fopen($filepath, 'w+');
            if (!$fp) {
                throw new Exception("Cannot open file for writing: " . $filepath);
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FILE => $fp,
                CURLOPT_SSL_VERIFYPEER => $options['ssl_verify'],
                CURLOPT_TIMEOUT => $options['timeout'],
                CURLOPT_USERAGENT => $this->defaultUserAgent,
                CURLOPT_FOLLOWLOCATION => true
            ]);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fp);

            if (!$result || $httpCode >= 400) {
                unlink($filepath); // Remove failed download
                throw new Exception("Download failed with HTTP code: " . $httpCode);
            }

            return [
                'success' => true,
                'filepath' => $filepath,
                'http_code' => $httpCode
            ];

        } catch (Exception $e) {
            error_log("Download error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload file
     */
    public function upload($url, $filePath, $fieldName = 'file', $options = []) {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: " . $filePath);
        }

        $data = [
            $fieldName => new CURLFile($filePath)
        ];

        $options['content_type'] = 'multipart';
        return $this->post($url, $data, $options);
    }
}



/**
 * Fluent HTTP Request Builder Class
 */
class HttpRequestBuilder {
    private $options = [];
    private $headers = [];
    private $url = null;
    
    /**
     * Set target URL
     */
    public function to($url) {
        $this->url = $url;
        return $this;
    }
    
    /**
     * Set URL (alias for to)
     */
    public function url($url) {
        return $this->to($url);
    }
    
    /**
     * Set timeout
     */
    public function timeout($seconds) {
        $this->options['timeout'] = $seconds;
        return $this;
    }
    
    /**
     * Set content type to JSON
     */
    public function json() {
        $this->options['content_type'] = 'json';
        return $this;
    }
    
    /**
     * Set content type to form
     */
    public function form() {
        $this->options['content_type'] = 'urlencoded';
        return $this;
    }
    
    /**
     * Set content type to multipart
     */
    public function multipart() {
        $this->options['content_type'] = 'multipart';
        return $this;
    }
    
    /**
     * Enable SSL verification
     */
    public function secure() {
        $this->options['ssl_verify'] = true;
        return $this;
    }
    
    /**
     * Disable SSL verification
     */
    public function insecure() {
        $this->options['ssl_verify'] = false;
        return $this;
    }
    
    /**
     * Add custom header
     */
    public function header($key, $value = null) {
        if (is_array($key)) {
            $this->headers = array_merge($this->headers, $key);
        } else {
            $this->headers[$key] = $value;
        }
        return $this;
    }
    
    /**
     * Add authorization header
     */
    public function auth($token, $type = 'Bearer') {
        $this->headers['Authorization'] = $type . ' ' . $token;
        return $this;
    }
    
    /**
     * Set user agent
     */
    public function userAgent($agent) {
        $this->options['user_agent'] = $agent;
        return $this;
    }
    
    /**
     * Execute GET request
     */
    public function get($url = null) {
        $targetUrl = $url ?: $this->url;
        if (!$targetUrl) {
            throw new InvalidArgumentException("URL is required");
        }
        
        if (!empty($this->headers)) {
            $this->options['headers'] = $this->headers;
        }
        
        return http('get', $targetUrl, null, $this->options);
    }
    
    /**
     * Execute POST request
     */
    public function post($data = null, $url = null) {
        $targetUrl = $url ?: $this->url;
        if (!$targetUrl) {
            throw new InvalidArgumentException("URL is required");
        }
        
        if (!empty($this->headers)) {
            $this->options['headers'] = $this->headers;
        }
        
        return http('post', $targetUrl, $data, $this->options);
    }
    
    /**
     * Execute PUT request
     */
    public function put($data = null, $url = null) {
        $targetUrl = $url ?: $this->url;
        if (!$targetUrl) {
            throw new InvalidArgumentException("URL is required");
        }
        
        if (!empty($this->headers)) {
            $this->options['headers'] = $this->headers;
        }
        
        return http('put', $targetUrl, $data, $this->options);
    }
    
    /**
     * Execute DELETE request
     */
    public function delete($url = null) {
        $targetUrl = $url ?: $this->url;
        if (!$targetUrl) {
            throw new InvalidArgumentException("URL is required");
        }
        
        if (!empty($this->headers)) {
            $this->options['headers'] = $this->headers;
        }
        
        return http('delete', $targetUrl, null, $this->options);
    }
}

