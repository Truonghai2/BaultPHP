<?php

declare(strict_types=1);

namespace Core\Attributes;

use ReflectionClass;

/**
 * PHP 8.3+ Features Support
 *
 * Utilities for working with PHP 8.3+ features:
 * - Readonly Classes
 * - Typed Class Constants
 * - Override Attribute
 * - Dynamic Class Constants
 *
 * Features:
 * - Readonly Classes support
 * - Typed Class Constants support
 * - Override Attribute support
 * - Dynamic Class Constants support
 */
class PHP83Features
{
    /**
     * Check if class is readonly
     */
    public function isReadonlyClass(string|object $class): bool
    {
        if (PHP_VERSION_ID < 80200) {
            return false; // Readonly classes require PHP 8.2+
        }

        $reflection = new ReflectionClass(is_object($class) ? get_class($class) : $class);
        return $reflection->isReadOnly();
    }

    /**
     * Check if property is readonly
     */
    public function isReadonlyProperty(string|object $class, string $property): bool
    {
        if (PHP_VERSION_ID < 80100) {
            return false; // Readonly properties require PHP 8.1+
        }

        $reflection = new ReflectionClass(is_object($class) ? get_class($class) : $class);
        
        if (!$reflection->hasProperty($property)) {
            return false;
        }

        $propertyReflection = $reflection->getProperty($property);
        return $propertyReflection->isReadOnly();
    }

    /**
     * Get typed class constants
     */
    public function getTypedConstants(string|object $class): array
    {
        if (PHP_VERSION_ID < 80300) {
            return []; // Typed class constants require PHP 8.3+
        }

        $reflection = new ReflectionClass(is_object($class) ? get_class($class) : $class);
        $constants = $reflection->getConstants();
        $typedConstants = [];

        foreach ($constants as $name => $value) {
            try {
                $constantReflection = $reflection->getReflectionConstant($name);
                if ($constantReflection && $constantReflection->getType()) {
                    $typedConstants[$name] = [
                        'value' => $value,
                        'type' => (string) $constantReflection->getType(),
                    ];
                }
            } catch (\Throwable $e) {
                // Constant doesn't support typed reflection
            }
        }

        return $typedConstants;
    }

    /**
     * Check if method has Override attribute
     */
    public function hasOverrideAttribute(string|object $class, string $method): bool
    {
        if (PHP_VERSION_ID < 80300) {
            return false; // Override attribute requires PHP 8.3+
        }

        $reflection = new ReflectionClass(is_object($class) ? get_class($class) : $class);
        
        if (!$reflection->hasMethod($method)) {
            return false;
        }

        $methodReflection = $reflection->getMethod($method);
        $attributes = $methodReflection->getAttributes(\Override::class);

        return !empty($attributes);
    }

    /**
     * Get dynamic class constants
     */
    public function getDynamicConstants(string|object $class): array
    {
        if (PHP_VERSION_ID < 80300) {
            return []; // Dynamic class constants require PHP 8.3+
        }

        $reflection = new ReflectionClass(is_object($class) ? get_class($class) : $class);
        $constants = $reflection->getConstants();
        $dynamicConstants = [];

        foreach ($constants as $name => $value) {
            try {
                $constantReflection = $reflection->getReflectionConstant($name);
                if ($constantReflection) {
                    // Check if constant is dynamic (computed at runtime)
                    // This is a heuristic check
                    $isDynamic = $this->isDynamicConstant($constantReflection, $value);
                    if ($isDynamic) {
                        $dynamicConstants[$name] = $value;
                    }
                }
            } catch (\Throwable $e) {
                // Skip
            }
        }

        return $dynamicConstants;
    }

    /**
     * Check if constant is dynamic
     */
    protected function isDynamicConstant($constantReflection, mixed $value): bool
    {
        // Heuristic: constants that reference other constants or functions are likely dynamic
        // This is a simplified check
        if (is_string($value)) {
            // Check if value contains function calls or other constants
            return str_contains($value, '(') || str_contains($value, '::');
        }

        return false;
    }

    /**
     * Validate readonly class immutability
     */
    public function validateReadonlyClass(string|object $class): array
    {
        if (!$this->isReadonlyClass($class)) {
            return [
                'valid' => false,
                'reason' => 'Class is not readonly',
            ];
        }

        $reflection = new ReflectionClass(is_object($class) ? get_class($class) : $class);
        $properties = $reflection->getProperties();
        $violations = [];

        foreach ($properties as $property) {
            if (!$property->isReadOnly() && !$property->isStatic()) {
                $violations[] = $property->getName();
            }
        }

        return [
            'valid' => empty($violations),
            'violations' => $violations,
        ];
    }

    /**
     * Get class feature summary
     */
    public function getClassFeatures(string|object $class): array
    {
        $reflection = new ReflectionClass(is_object($class) ? get_class($class) : $class);

        return [
            'readonly' => $this->isReadonlyClass($class),
            'typed_constants' => $this->getTypedConstants($class),
            'dynamic_constants' => $this->getDynamicConstants($class),
            'php_version' => PHP_VERSION,
            'features_available' => [
                'readonly_classes' => PHP_VERSION_ID >= 80200,
                'readonly_properties' => PHP_VERSION_ID >= 80100,
                'typed_constants' => PHP_VERSION_ID >= 80300,
                'override_attribute' => PHP_VERSION_ID >= 80300,
                'dynamic_constants' => PHP_VERSION_ID >= 80300,
            ],
        ];
    }
}
