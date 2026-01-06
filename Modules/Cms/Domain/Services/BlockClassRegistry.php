<?php

declare(strict_types=1);

namespace Modules\Cms\Domain\Services;

use Modules\Cms\Domain\Blocks\AbstractBlock;
use Psr\Log\LoggerInterface;

/**
 * Block Class Registry
 * 
 * Manages block class instances and validation with intelligent caching
 * Prevents repeated class_exists() checks and instantiation overhead
 * 
 * Performance optimizations:
 * - Singleton pattern for block instances (blocks are stateless)
 * - Cached class validation results
 * - Lazy loading of block classes
 */
class BlockClassRegistry
{
    /**
     * Cache of block instances
     * @var array<string, AbstractBlock>
     */
    private array $instances = [];

    /**
     * Cache of class validation results
     * @var array<string, bool>
     */
    private array $classExists = [];

    /**
     * Cache of instantiation errors
     * @var array<string, string>
     */
    private array $errors = [];

    public function __construct(
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Get or create a block instance
     * 
     * @param string $blockClass Fully qualified class name
     * @return AbstractBlock|null Returns null if class is invalid or instantiation fails
     */
    public function getInstance(string $blockClass): ?AbstractBlock
    {
        // Return cached instance if exists
        if (isset($this->instances[$blockClass])) {
            return $this->instances[$blockClass];
        }

        // Return null if we know it failed before
        if (isset($this->errors[$blockClass])) {
            return null;
        }

        // Check if class is valid (use cache)
        if (!$this->isValidBlockClass($blockClass)) {
            return null;
        }

        // Create and cache instance
        try {
            $instance = new $blockClass();
            
            // Verify it's actually a block
            if (!($instance instanceof AbstractBlock)) {
                $error = "Class {$blockClass} does not extend AbstractBlock";
                $this->errors[$blockClass] = $error;
                $this->classExists[$blockClass] = false;
                
                $this->logger?->warning('Invalid block class', [
                    'class' => $blockClass,
                    'error' => $error,
                ]);
                
                return null;
            }
            
            // Cache and return valid instance
            $this->instances[$blockClass] = $instance;
            return $instance;
            
        } catch (\Throwable $e) {
            // Mark as invalid and log error
            $this->errors[$blockClass] = $e->getMessage();
            $this->classExists[$blockClass] = false;
            
            $this->logger?->error('Failed to instantiate block class', [
                'class' => $blockClass,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return null;
        }
    }

    /**
     * Check if block class is valid (with caching)
     * 
     * @param string $blockClass Fully qualified class name
     * @return bool True if class exists, false otherwise
     */
    public function isValidBlockClass(string $blockClass): bool
    {
        // Empty class name is invalid
        if (empty($blockClass)) {
            return false;
        }

        // Check cache first
        if (!isset($this->classExists[$blockClass])) {
            $this->classExists[$blockClass] = class_exists($blockClass);
        }

        return $this->classExists[$blockClass];
    }

    /**
     * Preload multiple block classes for better performance
     * 
     * This is useful when you know which blocks will be needed
     * and want to warm up the cache in one go
     * 
     * @param array<string> $blockClasses Array of fully qualified class names
     * @return array<string, bool> Map of class name to success status
     */
    public function preload(array $blockClasses): array
    {
        $results = [];
        
        foreach ($blockClasses as $blockClass) {
            if (!empty($blockClass)) {
                $instance = $this->getInstance($blockClass);
                $results[$blockClass] = $instance !== null;
            }
        }
        
        return $results;
    }

    /**
     * Check if a block class has been cached
     * 
     * @param string $blockClass Fully qualified class name
     * @return bool True if instance is cached
     */
    public function isCached(string $blockClass): bool
    {
        return isset($this->instances[$blockClass]);
    }

    /**
     * Get error message for a failed block class
     * 
     * @param string $blockClass Fully qualified class name
     * @return string|null Error message or null if no error
     */
    public function getError(string $blockClass): ?string
    {
        return $this->errors[$blockClass] ?? null;
    }

    /**
     * Clear all cached instances and validation results
     * 
     * Use this sparingly as it will force re-instantiation of all blocks
     * 
     * @return void
     */
    public function clear(): void
    {
        $this->instances = [];
        $this->classExists = [];
        $this->errors = [];
    }

    /**
     * Clear cache for a specific block class
     * 
     * @param string $blockClass Fully qualified class name
     * @return void
     */
    public function clearClass(string $blockClass): void
    {
        unset($this->instances[$blockClass]);
        unset($this->classExists[$blockClass]);
        unset($this->errors[$blockClass]);
    }

    /**
     * Get comprehensive statistics about cached blocks
     * 
     * @return array<string, int|array> Statistics array
     */
    public function getStats(): array
    {
        return [
            'cached_instances' => count($this->instances),
            'validated_classes' => count($this->classExists),
            'valid_classes' => count(array_filter($this->classExists)),
            'invalid_classes' => count($this->classExists) - count(array_filter($this->classExists)),
            'errors' => count($this->errors),
            'memory_usage' => $this->estimateMemoryUsage(),
        ];
    }

    /**
     * Get detailed information about all cached instances
     * 
     * @return array<string, array> Detailed information per block class
     */
    public function getDebugInfo(): array
    {
        $info = [];
        
        foreach ($this->instances as $className => $instance) {
            $info[$className] = [
                'class' => $className,
                'name' => $instance->getName(),
                'title' => $instance->getTitle(),
                'category' => $instance->getCategory(),
                'configurable' => $instance->isConfigurable(),
                'cacheable' => $instance->isCacheable(),
            ];
        }
        
        return $info;
    }

    /**
     * Estimate memory usage of cached instances
     * 
     * @return int Estimated bytes
     */
    private function estimateMemoryUsage(): int
    {
        $size = 0;
        
        // Rough estimation: each instance + caches
        $size += count($this->instances) * 1024; // ~1KB per instance
        $size += count($this->classExists) * 64; // ~64B per validation entry
        $size += count($this->errors) * 256; // ~256B per error entry
        
        return $size;
    }
}

