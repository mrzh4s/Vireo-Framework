<?php

use Vireo\Framework\Http\Curl;
// ============== CURL HELPER FUNCTIONS ==============

/**
 * Main HTTP helper function - handles multiple operations
 * 
 * Usage:
 * http('get', 'https://api.example.com/data')
 * http('post', 'https://api.example.com/users', ['name' => 'John'])
 * http('put', 'https://api.example.com/users/1', $data, ['timeout' => 60])
 */
if (!function_exists('http')) {
    function http($method, $url, $data = null, $options = []) {
        try {
            $curl = Curl::getInstance();
            
            switch (strtolower($method)) {
                case 'get':
                    return $curl->get($url, $options);
                case 'post':
                    return $curl->post($url, $data, $options);
                case 'put':
                    return $curl->put($url, $data, $options);
                case 'delete':
                    return $curl->delete($url, $options);
                case 'request':
                default:
                    return $curl->request($url, $data, strtoupper($method), $options);
            }
        } catch (Exception $e) {
            error_log("HTTP helper error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

/**
 * Quick GET request
 * 
 * Usage:
 * $data = http_get('https://api.example.com/users')
 * $data = http_get('https://api.example.com/users', ['timeout' => 30])
 */
if (!function_exists('http_get')) {
    function http_get($url, $options = []) {
        return http('get', $url, null, $options);
    }
}

/**
 * Quick POST request
 * 
 * Usage:
 * $response = http_post('https://api.example.com/users', ['name' => 'John'])
 * $response = http_post('https://api.example.com/users', $jsonData, ['content_type' => 'json'])
 */
if (!function_exists('http_post')) {
    function http_post($url, $data, $options = []) {
        return http('post', $url, $data, $options);
    }
}

/**
 * Quick PUT request
 * 
 * Usage:
 * $response = http_put('https://api.example.com/users/1', ['name' => 'Jane'])
 */
if (!function_exists('http_put')) {
    function http_put($url, $data, $options = []) {
        return http('put', $url, $data, $options);
    }
}

/**
 * Quick DELETE request
 * 
 * Usage:
 * $response = http_delete('https://api.example.com/users/1')
 */
if (!function_exists('http_delete')) {
    function http_delete($url, $options = []) {
        return http('delete', $url, null, $options);
    }
}

/**
 * Download file helper
 * 
 * Usage:
 * download('https://example.com/file.pdf', '/path/to/save/file.pdf')
 */
if (!function_exists('download')) {
    function download($url, $filepath, $options = []) {
        try {
            $curl = Curl::getInstance();
            return $curl->download($url, $filepath, $options);
        } catch (Exception $e) {
            error_log("Download helper error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

/**
 * Upload file helper
 * 
 * Usage:
 * upload('https://api.example.com/upload', '/path/to/file.jpg')
 * upload('https://api.example.com/upload', '/path/to/file.jpg', 'image')
 */
if (!function_exists('upload')) {
    function upload($url, $filePath, $fieldName = 'file', $options = []) {
        try {
            $curl = Curl::getInstance();
            return $curl->upload($url, $filePath, $fieldName, $options);
        } catch (Exception $e) {
            error_log("Upload helper error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

/**
 * HTTP helper with fluent interface
 * 
 * Usage:
 * $response = http_request()->to('https://api.example.com/users')->post(['name' => 'John']);
 * $response = http_request()->timeout(60)->json()->get('https://api.example.com/data');
 */
if (!function_exists('http_request')) {
    function http_request() {
        return new HttpRequestBuilder();
    }
}

// ============== UTILITY HELPERS ==============

/**
 * Check if response is successful
 * 
 * Usage:
 * if (is_success_response($response)) { ... }
 */
if (!function_exists('is_success_response')) {
    function is_success_response($response) {
        if (!is_array($response)) {
            return false;
        }
        
        return isset($response['status']) && 
               in_array($response['status'], ['success', 'true', true]) &&
               (!isset($response['http_code']) || ($response['http_code'] >= 200 && $response['http_code'] < 300));
    }
}

/**
 * Extract JSON from response
 * 
 * Usage:
 * $data = response_json($response)
 */
if (!function_exists('response_json')) {
    function response_json($response, $default = null) {
        if (!is_array($response) || !isset($response['response'])) {
            return $default;
        }
        
        $decoded = json_decode($response['response'], true);
        return $decoded !== null ? $decoded : $default;
    }
}

/**
 * Get response body
 * 
 * Usage:
 * $body = response_body($response)
 */
if (!function_exists('response_body')) {
    function response_body($response, $default = '') {
        if (!is_array($response)) {
            return $default;
        }
        
        return $response['response'] ?? $default;
    }
}

/**
 * Get response status code
 * 
 * Usage:
 * $code = response_code($response)
 */
if (!function_exists('response_code')) {
    function response_code($response) {
        if (!is_array($response)) {
            return null;
        }
        
        return $response['http_code'] ?? null;
    }
}

/**
 * Build query string from array
 * 
 * Usage:
 * $url = 'https://api.example.com/users?' . build_query(['page' => 1, 'limit' => 10])
 */
if (!function_exists('build_query')) {
    function build_query($data) {
        return http_build_query($data);
    }
}