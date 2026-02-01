<?php

namespace Vireo\Framework\Validation;

/**
 * FileValidator - File upload validation service
 *
 * Singleton service for validating uploaded files including MIME types,
 * file sizes, extensions, and image dimensions.
 * Follows Vireo Framework's singleton pattern.
 */
class FileValidator
{
    /**
     * @var FileValidator|null Singleton instance
     */
    private static ?FileValidator $instance = null;

    /**
     * @var ErrorBag Validation errors
     */
    private ErrorBag $errors;

    /**
     * @var array Configuration
     */
    private array $config = [];

    /**
     * Get singleton instance
     *
     * @return FileValidator
     */
    public static function getInstance(): FileValidator
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Private constructor - Singleton pattern
     */
    private function __construct()
    {
        $this->errors = new ErrorBag();
        $this->loadConfiguration();
    }

    /**
     * Load file validation configuration
     *
     * @return void
     */
    private function loadConfiguration(): void
    {
        $configPath = defined('ROOT_PATH') ? ROOT_PATH . '/Config/Validation.php' : __DIR__ . '/../../Config/Validation.php';

        if (file_exists($configPath)) {
            $config = require $configPath;
            $this->config = $config['files'] ?? [];
        } else {
            $this->config = [
                'max_size' => 10240, // 10MB default
                'allowed_mimes' => [],
                'allowed_extensions' => [],
            ];
        }
    }

    /**
     * Validate a single file upload
     *
     * @param array $file File from $_FILES
     * @param array $rules Validation rules
     * @return bool
     */
    public function validate(array $file, array $rules): bool
    {
        $this->errors->clear();

        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $this->errors->add('file', 'No file was uploaded.');
            return false;
        }

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors->add('file', $this->getUploadErrorMessage($file['error']));
            return false;
        }

        // Apply rules
        foreach ($rules as $rule => $params) {
            $method = 'validate' . ucfirst($rule);

            if (method_exists($this, $method)) {
                if (!$this->$method($file, $params)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Validate multiple file uploads
     *
     * @param array $files Files from $_FILES
     * @param array $rules Validation rules
     * @return bool
     */
    public function validateMultiple(array $files, array $rules): bool
    {
        $this->errors->clear();

        foreach ($files as $index => $file) {
            if (!$this->validate($file, $rules)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate MIME types
     *
     * @param array $file File data
     * @param array|string $allowedMimes Allowed MIME types
     * @return bool
     */
    public function mimes(array $file, array|string $allowedMimes): bool
    {
        if (is_string($allowedMimes)) {
            $allowedMimes = explode(',', $allowedMimes);
        }

        $mimeType = $this->getMimeType($file);

        if (!in_array($mimeType, $allowedMimes)) {
            $this->errors->add('file', "The file must be of type: " . implode(', ', $allowedMimes));
            return false;
        }

        return true;
    }

    /**
     * Validate maximum file size
     *
     * @param array $file File data
     * @param int $maxSizeKb Maximum size in kilobytes
     * @return bool
     */
    public function maxSize(array $file, int $maxSizeKb): bool
    {
        $fileSizeKb = $this->getFileSize($file);

        if ($fileSizeKb > $maxSizeKb) {
            $maxSizeMb = round($maxSizeKb / 1024, 2);
            $this->errors->add('file', "The file may not be larger than {$maxSizeMb}MB.");
            return false;
        }

        return true;
    }

    /**
     * Validate minimum file size
     *
     * @param array $file File data
     * @param int $minSizeKb Minimum size in kilobytes
     * @return bool
     */
    public function minSize(array $file, int $minSizeKb): bool
    {
        $fileSizeKb = $this->getFileSize($file);

        if ($fileSizeKb < $minSizeKb) {
            $minSizeMb = round($minSizeKb / 1024, 2);
            $this->errors->add('file', "The file must be at least {$minSizeMb}MB.");
            return false;
        }

        return true;
    }

    /**
     * Validate file extensions
     *
     * @param array $file File data
     * @param array|string $allowedExtensions Allowed extensions
     * @return bool
     */
    public function extensions(array $file, array|string $allowedExtensions): bool
    {
        if (is_string($allowedExtensions)) {
            $allowedExtensions = explode(',', $allowedExtensions);
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions)) {
            $this->errors->add('file', "The file must have one of these extensions: " . implode(', ', $allowedExtensions));
            return false;
        }

        return true;
    }

    /**
     * Validate image dimensions
     *
     * @param array $file File data
     * @param array $constraints Dimension constraints (e.g., ['min_width' => 100, 'max_height' => 500])
     * @return bool
     */
    public function dimensions(array $file, array $constraints): bool
    {
        $dimensions = $this->getImageDimensions($file);

        if ($dimensions === null) {
            $this->errors->add('file', 'The file must be an image.');
            return false;
        }

        [$width, $height] = $dimensions;

        if (isset($constraints['min_width']) && $width < $constraints['min_width']) {
            $this->errors->add('file', "Image width must be at least {$constraints['min_width']}px.");
            return false;
        }

        if (isset($constraints['max_width']) && $width > $constraints['max_width']) {
            $this->errors->add('file', "Image width may not exceed {$constraints['max_width']}px.");
            return false;
        }

        if (isset($constraints['min_height']) && $height < $constraints['min_height']) {
            $this->errors->add('file', "Image height must be at least {$constraints['min_height']}px.");
            return false;
        }

        if (isset($constraints['max_height']) && $height > $constraints['max_height']) {
            $this->errors->add('file', "Image height may not exceed {$constraints['max_height']}px.");
            return false;
        }

        if (isset($constraints['width']) && $width !== $constraints['width']) {
            $this->errors->add('file', "Image width must be exactly {$constraints['width']}px.");
            return false;
        }

        if (isset($constraints['height']) && $height !== $constraints['height']) {
            $this->errors->add('file', "Image height must be exactly {$constraints['height']}px.");
            return false;
        }

        return true;
    }

    /**
     * Get file MIME type
     *
     * @param array $file File data
     * @return string|null
     */
    private function getMimeType(array $file): ?string
    {
        if (function_exists('mime_content_type')) {
            return mime_content_type($file['tmp_name']);
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            return $mime;
        }

        return $file['type'] ?? null;
    }

    /**
     * Get file size in kilobytes
     *
     * @param array $file File data
     * @return int
     */
    private function getFileSize(array $file): int
    {
        return isset($file['size']) ? (int) ceil($file['size'] / 1024) : 0;
    }

    /**
     * Get image dimensions
     *
     * @param array $file File data
     * @return array|null [width, height] or null if not an image
     */
    private function getImageDimensions(array $file): ?array
    {
        if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            return null;
        }

        $imageInfo = @getimagesize($file['tmp_name']);

        if ($imageInfo === false) {
            return null;
        }

        return [$imageInfo[0], $imageInfo[1]];
    }

    /**
     * Get upload error message
     *
     * @param int $errorCode PHP upload error code
     * @return string
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the maximum allowed size.',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the form maximum size.',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder for file upload.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
            default => 'An unknown error occurred during file upload.',
        };
    }

    /**
     * Get validation errors
     *
     * @return ErrorBag
     */
    public function errors(): ErrorBag
    {
        return $this->errors;
    }

    /**
     * Check if validation passed
     *
     * @return bool
     */
    public function passes(): bool
    {
        return $this->errors->isEmpty();
    }

    /**
     * Check if validation failed
     *
     * @return bool
     */
    public function fails(): bool
    {
        return !$this->passes();
    }
}
