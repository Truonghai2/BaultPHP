<?php

namespace Core\Security;

use RuntimeException;

/**
 * File Security Validator.
 * 
 * Validates uploaded files against polyglot attacks, MIME spoofing,
 * and malicious content.
 */
class FileValidator
{
    /**
     * Validate uploaded file is safe.
     * 
     * @param string $filePath Path to uploaded file
     * @param string $expectedMime Expected MIME type
     * @param array $options Validation options
     * @return array ['valid' => bool, 'errors' => array, 'detected_mime' => string]
     */
    public static function validate(string $filePath, string $expectedMime, array $options = []): array
    {
        $errors = [];

        if (!file_exists($filePath)) {
            return ['valid' => false, 'errors' => ['File does not exist']];
        }

        // 1. Server-side MIME detection (not client-provided!)
        $detectedMime = self::detectMimeType($filePath);

        if (!$detectedMime) {
            $errors[] = 'Could not detect MIME type';
            return ['valid' => false, 'errors' => $errors, 'detected_mime' => null];
        }

        // 2. MIME type validation
        if ($detectedMime !== $expectedMime) {
            // Check if it's an acceptable alias
            if (!self::isMimeAlias($detectedMime, $expectedMime)) {
                $errors[] = "MIME type mismatch: expected {$expectedMime}, got {$detectedMime}";
            }
        }

        // 3. Check for polyglot/hybrid files
        if (self::isPolyglot($filePath, $detectedMime)) {
            $errors[] = 'File appears to be a polyglot (multi-format) file';
        }

        // 4. Check for embedded PHP
        if (self::containsPhpCode($filePath)) {
            $errors[] = 'File contains PHP code';
        }

        // 5. Image-specific validation
        if (str_starts_with($detectedMime, 'image/')) {
            $imageErrors = self::validateImage($filePath);
            $errors = array_merge($errors, $imageErrors);
        }

        // 6. Check file size
        $maxSize = $options['max_size'] ?? 10485760; // 10MB default
        if (filesize($filePath) > $maxSize) {
            $errors[] = 'File exceeds maximum size';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'detected_mime' => $detectedMime,
        ];
    }

    /**
     * Detect MIME type using server-side detection (finfo).
     */
    public static function detectMimeType(string $filePath): ?string
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (!$finfo) {
            return null;
        }

        $mime = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        return $mime ?: null;
    }

    /**
     * Check if detected MIME is an acceptable alias.
     */
    protected static function isMimeAlias(string $detected, string $expected): bool
    {
        $aliases = [
            'image/jpg' => 'image/jpeg',
            'image/x-png' => 'image/png',
        ];

        return isset($aliases[$detected]) && $aliases[$detected] === $expected;
    }

    /**
     * Check for polyglot files (files valid in multiple formats).
     */
    public static function isPolyglot(string $filePath, string $detectedMime): bool
    {
        // Read file header
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return false;
        }

        $header = fread($handle, 512);
        fclose($handle);

        // Check for multiple file signatures
        $signatures = [
            'PDF' => "\x25\x50\x44\x46",              // %PDF
            'ZIP' => "\x50\x4B\x03\x04",              // PK..
            'EXE' => "\x4D\x5A",                      // MZ
            'PHP' => "<?php",                          // PHP opening tag
            'HTML' => "<!DOCTYPE",                     // HTML
            'HTML2' => "<html",                        // HTML
            'SCRIPT' => "<script",                     // JavaScript
        ];

        $matchCount = 0;
        foreach ($signatures as $format => $sig) {
            if (str_contains($header, $sig)) {
                $matchCount++;
                
                // If image MIME but contains script/executable signature
                if (str_starts_with($detectedMime, 'image/') && in_array($format, ['EXE', 'PHP', 'SCRIPT'])) {
                    return true;
                }
            }
        }

        // If multiple signatures detected (polyglot)
        return $matchCount > 1;
    }

    /**
     * Check if file contains PHP code.
     */
    public static function containsPhpCode(string $filePath): bool
    {
        $content = file_get_contents($filePath);
        
        if ($content === false) {
            return false;
        }

        // Check for PHP opening tags
        $phpPatterns = [
            '/<\?php/i',
            '/<\?=/i',
            '/<\?/i',
            '/<script\s+language\s*=\s*["\']?php["\']?/i',
        ];

        foreach ($phpPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate image files.
     */
    protected static function validateImage(string $filePath): array
    {
        $errors = [];

        // Try to read image info
        $imageInfo = @getimagesize($filePath);

        if ($imageInfo === false) {
            $errors[] = 'File is not a valid image';
            return $errors;
        }

        // Verify MIME from getimagesize matches
        $imageMime = $imageInfo['mime'] ?? null;
        $detectedMime = self::detectMimeType($filePath);

        if ($imageMime && $detectedMime && $imageMime !== $detectedMime) {
            $errors[] = 'Image MIME type inconsistency detected';
        }

        // Check for suspicious dimensions
        if (isset($imageInfo[0], $imageInfo[1])) {
            // Reject impossibly large dimensions (potential DoS)
            if ($imageInfo[0] > 50000 || $imageInfo[1] > 50000) {
                $errors[] = 'Image dimensions too large (potential DoS)';
            }

            // Reject zero-dimension images
            if ($imageInfo[0] === 0 || $imageInfo[1] === 0) {
                $errors[] = 'Invalid image dimensions';
            }
        }

        return $errors;
    }

    /**
     * Re-encode image to strip malicious content.
     * 
     * @param string $inputPath Path to input image
     * @param string $outputPath Path to save cleaned image
     * @param array $options Encoding options
     * @return bool Success
     */
    public static function sanitizeImage(string $inputPath, string $outputPath, array $options = []): bool
    {
        $imageInfo = @getimagesize($inputPath);
        
        if ($imageInfo === false) {
            return false;
        }

        // Create image resource based on type
        $image = match ($imageInfo[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($inputPath),
            IMAGETYPE_PNG => @imagecreatefrompng($inputPath),
            IMAGETYPE_GIF => @imagecreatefromgif($inputPath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') 
                ? @imagecreatefromwebp($inputPath) 
                : false,
            default => false,
        };

        if ($image === false) {
            return false;
        }

        // Strip all metadata by re-encoding
        $quality = $options['quality'] ?? 90;
        $format = $options['format'] ?? 'jpeg';

        $success = match ($format) {
            'jpeg', 'jpg' => imagejpeg($image, $outputPath, $quality),
            'png' => imagepng($image, $outputPath, (int)(9 - ($quality / 10))),
            'webp' => function_exists('imagewebp') 
                ? imagewebp($image, $outputPath, $quality) 
                : false,
            default => false,
        };

        imagedestroy($image);

        return $success;
    }
}
