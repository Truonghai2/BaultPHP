<?php

namespace Core\Streaming;

/**
 * Compression Helper for Events.
 * 
 * Compresses large events to reduce:
 * - Network bandwidth
 * - Storage size
 * - Transmission time
 */
class CompressionHelper
{
    public function __construct(
        private bool $enabled = true,
        private string $algorithm = 'gzip',
        private int $level = 6,
        private int $threshold = 1024,
    ) {
    }

    /**
     * Compress data if above threshold.
     */
    public function compress(string $data): array
    {
        $originalSize = strlen($data);

        // Skip compression if below threshold
        if (!$this->enabled || $originalSize < $this->threshold) {
            return [
                'data' => $data,
                'compressed' => false,
                'algorithm' => null,
                'original_size' => $originalSize,
                'compressed_size' => $originalSize,
                'ratio' => 1.0,
            ];
        }

        $compressed = match ($this->algorithm) {
            'gzip' => gzencode($data, $this->level),
            'zstd' => function_exists('zstd_compress') ? zstd_compress($data, $this->level) : null,
            'deflate' => gzdeflate($data, $this->level),
            default => null,
        };

        if ($compressed === null || $compressed === false) {
            // Compression failed, return original
            return [
                'data' => $data,
                'compressed' => false,
                'algorithm' => null,
                'original_size' => $originalSize,
                'compressed_size' => $originalSize,
                'ratio' => 1.0,
            ];
        }

        $compressedSize = strlen($compressed);
        $ratio = $originalSize / $compressedSize;

        return [
            'data' => $compressed,
            'compressed' => true,
            'algorithm' => $this->algorithm,
            'original_size' => $originalSize,
            'compressed_size' => $compressedSize,
            'ratio' => round($ratio, 2),
        ];
    }

    /**
     * Decompress data.
     */
    public function decompress(string $data, ?string $algorithm = null): string
    {
        if ($algorithm === null) {
            // Not compressed
            return $data;
        }

        return match ($algorithm) {
            'gzip' => gzdecode($data),
            'zstd' => function_exists('zstd_uncompress') ? zstd_uncompress($data) : $data,
            'deflate' => gzinflate($data),
            default => $data,
        };
    }

    /**
     * Check if compression is beneficial.
     */
    public function shouldCompress(string $data): bool
    {
        return $this->enabled && strlen($data) >= $this->threshold;
    }

    /**
     * Get compression ratio estimate.
     */
    public function estimateRatio(string $data): float
    {
        if (!$this->shouldCompress($data)) {
            return 1.0;
        }

        // Sample first 1KB for quick estimate
        $sample = substr($data, 0, 1024);
        $compressed = gzencode($sample, $this->level);
        
        if ($compressed === false) {
            return 1.0;
        }

        return strlen($sample) / strlen($compressed);
    }
}
