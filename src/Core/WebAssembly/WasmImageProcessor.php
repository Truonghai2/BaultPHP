<?php

declare(strict_types=1);

namespace Core\WebAssembly;

use Core\Application;
use RuntimeException;

/**
 * WASM Image Processor
 *
 * High-performance image processing using WebAssembly.
 * Replaces Intervention Image for compute-intensive operations.
 */
class WasmImageProcessor
{
    protected WasmExecutor $executor;
    protected array $config = [];

    public function __construct(
        Application $app,
        ?WasmExecutor $executor = null,
    ) {
        $this->config = config('wasm.image', []);
        $this->executor = $executor ?? $app->make(WasmExecutor::class);
    }

    /**
     * Resize image
     *
     * @param string $imagePath Path to image file
     * @param int $width Target width
     * @param int $height Target height
     * @param array $options Resize options
     * @return string Path to resized image
     */
    public function resize(string $imagePath, int $width, int $height, array $options = []): string
    {
        if (!$this->executor->isAvailable()) {
            return $this->fallbackResize($imagePath, $width, $height, $options);
        }

        $imageData = file_get_contents($imagePath);
        if ($imageData === false) {
            throw new RuntimeException("Failed to read image: {$imagePath}");
        }

        $inputs = [
            'image_data' => base64_encode($imageData),
            'width' => $width,
            'height' => $height,
            'quality' => $options['quality'] ?? 90,
            'format' => $options['format'] ?? 'jpeg',
            'preserve_aspect' => $options['preserve_aspect'] ?? true,
        ];

        $result = $this->executor->execute('image_processor.wasm', $inputs, [
            'function' => 'resize',
            'output_format' => 'json',
        ]);

        if (!isset($result['image_data'])) {
            throw new RuntimeException("WASM image resize failed");
        }

        $outputPath = $options['output_path'] ?? $this->generateOutputPath($imagePath, $width, $height);
        file_put_contents($outputPath, base64_decode($result['image_data']));

        return $outputPath;
    }

    /**
     * Crop image
     *
     * @param string $imagePath Path to image file
     * @param int $x X coordinate
     * @param int $y Y coordinate
     * @param int $width Crop width
     * @param int $height Crop height
     * @param array $options Crop options
     * @return string Path to cropped image
     */
    public function crop(string $imagePath, int $x, int $y, int $width, int $height, array $options = []): string
    {
        if (!$this->executor->isAvailable()) {
            return $this->fallbackCrop($imagePath, $x, $y, $width, $height, $options);
        }

        $imageData = file_get_contents($imagePath);
        if ($imageData === false) {
            throw new RuntimeException("Failed to read image: {$imagePath}");
        }

        $inputs = [
            'image_data' => base64_encode($imageData),
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'quality' => $options['quality'] ?? 90,
        ];

        $result = $this->executor->execute('image_processor.wasm', $inputs, [
            'function' => 'crop',
            'output_format' => 'json',
        ]);

        if (!isset($result['image_data'])) {
            throw new RuntimeException("WASM image crop failed");
        }

        $outputPath = $options['output_path'] ?? $this->generateOutputPath($imagePath, "crop_{$x}_{$y}_{$width}_{$height}");
        file_put_contents($outputPath, base64_decode($result['image_data']));

        return $outputPath;
    }

    /**
     * Apply filter to image
     *
     * @param string $imagePath Path to image file
     * @param string $filter Filter name (blur, sharpen, grayscale, etc.)
     * @param array $options Filter options
     * @return string Path to filtered image
     */
    public function filter(string $imagePath, string $filter, array $options = []): string
    {
        if (!$this->executor->isAvailable()) {
            return $this->fallbackFilter($imagePath, $filter, $options);
        }

        $imageData = file_get_contents($imagePath);
        if ($imageData === false) {
            throw new RuntimeException("Failed to read image: {$imagePath}");
        }

        $inputs = [
            'image_data' => base64_encode($imageData),
            'filter' => $filter,
            'intensity' => $options['intensity'] ?? 1.0,
        ];

        $result = $this->executor->execute('image_processor.wasm', $inputs, [
            'function' => 'filter',
            'output_format' => 'json',
        ]);

        if (!isset($result['image_data'])) {
            throw new RuntimeException("WASM image filter failed");
        }

        $outputPath = $options['output_path'] ?? $this->generateOutputPath($imagePath, $filter);
        file_put_contents($outputPath, base64_decode($result['image_data']));

        return $outputPath;
    }

    /**
     * Generate output path
     */
    protected function generateOutputPath(string $originalPath, string|int $suffix): string
    {
        $pathInfo = pathinfo($originalPath);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? 'jpg';

        return $directory . '/' . $filename . '_' . $suffix . '.' . $extension;
    }

    /**
     * Fallback to PHP implementation (Intervention Image)
     */
    protected function fallbackResize(string $imagePath, int $width, int $height, array $options): string
    {
        if (!class_exists(\Intervention\Image\ImageManager::class)) {
            throw new RuntimeException("Intervention Image not available for fallback");
        }

        $manager = new \Intervention\Image\ImageManager(['driver' => 'gd']);
        $image = $manager->make($imagePath);
        $image->resize($width, $height, function ($constraint) use ($options) {
            if ($options['preserve_aspect'] ?? true) {
                $constraint->aspectRatio();
            }
        });

        $outputPath = $options['output_path'] ?? $this->generateOutputPath($imagePath, "{$width}x{$height}");
        $image->save($outputPath, $options['quality'] ?? 90);

        return $outputPath;
    }

    protected function fallbackCrop(string $imagePath, int $x, int $y, int $width, int $height, array $options): string
    {
        if (!class_exists(\Intervention\Image\ImageManager::class)) {
            throw new RuntimeException("Intervention Image not available for fallback");
        }

        $manager = new \Intervention\Image\ImageManager(['driver' => 'gd']);
        $image = $manager->make($imagePath);
        $image->crop($width, $height, $x, $y);

        $outputPath = $options['output_path'] ?? $this->generateOutputPath($imagePath, "crop_{$x}_{$y}_{$width}_{$height}");
        $image->save($outputPath, $options['quality'] ?? 90);

        return $outputPath;
    }

    protected function fallbackFilter(string $imagePath, string $filter, array $options): string
    {
        if (!class_exists(\Intervention\Image\ImageManager::class)) {
            throw new RuntimeException("Intervention Image not available for fallback");
        }

        $manager = new \Intervention\Image\ImageManager(['driver' => 'gd']);
        $image = $manager->make($imagePath);
        
        match ($filter) {
            'blur' => $image->blur($options['intensity'] ?? 1),
            'sharpen' => $image->sharpen($options['intensity'] ?? 10),
            'grayscale' => $image->greyscale(),
            default => throw new RuntimeException("Unknown filter: {$filter}"),
        };

        $outputPath = $options['output_path'] ?? $this->generateOutputPath($imagePath, $filter);
        $image->save($outputPath);

        return $outputPath;
    }
}
