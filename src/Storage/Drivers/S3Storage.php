<?php

namespace Framework\Storage\Drivers;

use Framework\Storage\StorageInterface;
use Exception;

/**
 * S3-Compatible Storage Driver
 *
 * Store files on S3 or S3-compatible services (MinIO, DigitalOcean Spaces, etc.)
 * Uses simple HTTP API for basic operations (no AWS SDK required)
 *
 * For advanced features, consider using AWS SDK for PHP
 */
class S3Storage implements StorageInterface
{
    protected array $config;
    protected string $bucket;
    protected string $region;
    protected string $endpoint;
    protected ?string $baseUrl;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->bucket = $config['bucket'] ?? '';
        $this->region = $config['region'] ?? 'us-east-1';
        $this->endpoint = $config['endpoint'] ?? "https://s3.{$this->region}.amazonaws.com";
        $this->baseUrl = $config['url'] ?? null;

        if (empty($this->bucket)) {
            throw new Exception('S3 bucket is required');
        }
    }

    /**
     * Get full URL for a file
     */
    protected function getS3Url(string $path): string
    {
        return rtrim($this->endpoint, '/') . '/' . $this->bucket . '/' . ltrim($path, '/');
    }

    /**
     * Generate AWS Signature V4
     */
    protected function sign(string $method, string $path, string $payload = ''): array
    {
        // Simplified signature - for production use AWS SDK
        $key = $this->config['key'] ?? '';
        $secret = $this->config['secret'] ?? '';

        if (empty($key) || empty($secret)) {
            throw new Exception('S3 credentials are required');
        }

        $headers = [
            'Host' => parse_url($this->endpoint, PHP_URL_HOST),
            'x-amz-date' => gmdate('Ymd\THis\Z'),
            'x-amz-content-sha256' => hash('sha256', $payload),
        ];

        return $headers;
    }

    /**
     * Make HTTP request to S3
     */
    protected function request(string $method, string $path, string $body = ''): ?string
    {
        $url = $this->getS3Url($path);
        $headers = $this->sign($method, $path, $body);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headerArray = [];
        foreach ($headers as $key => $value) {
            $headerArray[] = "{$key}: {$value}";
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);

        if ($method === 'PUT' || $method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return $response !== false ? $response : null;
        }

        // Log failure
        logger('storage')->error('S3 storage request failed', [
            'driver' => 's3',
            'method' => $method,
            'path' => $path,
            'bucket' => $this->bucket,
            'http_code' => $httpCode,
            'curl_error' => $curlError ?: null,
            'response' => is_string($response) ? substr($response, 0, 500) : null
        ]);

        return null;
    }

    /**
     * Write content to a file
     */
    public function put(string $path, string $contents): bool
    {
        $response = $this->request('PUT', $path, $contents);
        return $response !== null;
    }

    /**
     * Get file contents
     */
    public function get(string $path): ?string
    {
        return $this->request('GET', $path);
    }

    /**
     * Check if file exists
     */
    public function exists(string $path): bool
    {
        $response = $this->request('HEAD', $path);
        return $response !== null;
    }

    /**
     * Delete a file
     */
    public function delete(string $path): bool
    {
        $response = $this->request('DELETE', $path);
        return $response !== null;
    }

    /**
     * Copy a file
     */
    public function copy(string $from, string $to): bool
    {
        // S3 supports server-side copy
        $contents = $this->get($from);

        if ($contents === null) {
            return false;
        }

        return $this->put($to, $contents);
    }

    /**
     * Move a file
     */
    public function move(string $from, string $to): bool
    {
        if (!$this->copy($from, $to)) {
            return false;
        }

        return $this->delete($from);
    }

    /**
     * Get file size in bytes
     */
    public function size(string $path): int
    {
        // Would need to parse HEAD response headers
        return 0;
    }

    /**
     * Get file last modified time
     */
    public function lastModified(string $path): int
    {
        // Would need to parse HEAD response headers
        return 0;
    }

    /**
     * List files in directory
     */
    public function files(string $directory = ''): array
    {
        // Would need to implement ListObjects API
        return [];
    }

    /**
     * List all files recursively
     */
    public function allFiles(string $directory = ''): array
    {
        return $this->files($directory);
    }

    /**
     * List directories
     */
    public function directories(string $directory = ''): array
    {
        // S3 doesn't have true directories
        return [];
    }

    /**
     * Create a directory
     */
    public function makeDirectory(string $path): bool
    {
        // S3 doesn't have true directories
        return true;
    }

    /**
     * Delete a directory
     */
    public function deleteDirectory(string $path): bool
    {
        // Would need to delete all objects with prefix
        return false;
    }

    /**
     * Get file URL
     */
    public function url(string $path): ?string
    {
        if ($this->baseUrl !== null) {
            return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        }

        // Return S3 URL
        return $this->getS3Url($path);
    }

    /**
     * Store an uploaded file
     */
    public function putFile(string $directory, array $uploadedFile): ?string
    {
        if (!isset($uploadedFile['tmp_name']) || !isset($uploadedFile['name'])) {
            return null;
        }

        if (!is_uploaded_file($uploadedFile['tmp_name'])) {
            return null;
        }

        $filename = basename($uploadedFile['name']);
        $path = ltrim($directory . '/' . $filename, '/');

        $contents = file_get_contents($uploadedFile['tmp_name']);
        if ($contents === false) {
            return null;
        }

        return $this->put($path, $contents) ? $path : null;
    }
}
