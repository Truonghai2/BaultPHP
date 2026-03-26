<?php

declare(strict_types=1);

namespace Core\Attributes;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * PHP Attributes Enhancement
 *
 * Provides utilities for working with PHP attributes:
 * - Rich metadata extraction
 * - Code generation từ attributes
 * - Runtime optimization
 *
 * Features:
 * - Rich metadata với attributes
 * - Code generation từ attributes
 * - Runtime optimization
 */
class AttributeEnhancer
{
    protected array $attributeCache = [];

    /**
     * Get all attributes from a class
     *
     * @param string|object $class Class name or instance
     * @param string|null $attributeClass Filter by attribute class
     * @return array Array of attribute instances
     */
    public function getClassAttributes(string|object $class, ?string $attributeClass = null): array
    {
        $className = is_object($class) ? get_class($class) : $class;
        $cacheKey = "class:{$className}:" . ($attributeClass ?? 'all');

        if (isset($this->attributeCache[$cacheKey])) {
            return $this->attributeCache[$cacheKey];
        }

        $reflection = new ReflectionClass($className);
        $attributes = $reflection->getAttributes($attributeClass);

        $instances = array_map(fn(ReflectionAttribute $attr) => $attr->newInstance(), $attributes);

        $this->attributeCache[$cacheKey] = $instances;
        return $instances;
    }

    /**
     * Get all attributes from a method
     *
     * @param string|object $class Class name or instance
     * @param string $method Method name
     * @param string|null $attributeClass Filter by attribute class
     * @return array Array of attribute instances
     */
    public function getMethodAttributes(string|object $class, string $method, ?string $attributeClass = null): array
    {
        $className = is_object($class) ? get_class($class) : $class;
        $cacheKey = "method:{$className}::{$method}:" . ($attributeClass ?? 'all');

        if (isset($this->attributeCache[$cacheKey])) {
            return $this->attributeCache[$cacheKey];
        }

        $reflection = new ReflectionClass($className);
        if (!$reflection->hasMethod($method)) {
            return [];
        }

        $methodReflection = $reflection->getMethod($method);
        $attributes = $methodReflection->getAttributes($attributeClass);

        $instances = array_map(fn(ReflectionAttribute $attr) => $attr->newInstance(), $attributes);

        $this->attributeCache[$cacheKey] = $instances;
        return $instances;
    }

    /**
     * Get all attributes from a property
     *
     * @param string|object $class Class name or instance
     * @param string $property Property name
     * @param string|null $attributeClass Filter by attribute class
     * @return array Array of attribute instances
     */
    public function getPropertyAttributes(string|object $class, string $property, ?string $attributeClass = null): array
    {
        $className = is_object($class) ? get_class($class) : $class;
        $cacheKey = "property:{$className}::{$property}:" . ($attributeClass ?? 'all');

        if (isset($this->attributeCache[$cacheKey])) {
            return $this->attributeCache[$cacheKey];
        }

        $reflection = new ReflectionClass($className);
        if (!$reflection->hasProperty($property)) {
            return [];
        }

        $propertyReflection = $reflection->getProperty($property);
        $attributes = $propertyReflection->getAttributes($attributeClass);

        $instances = array_map(fn(ReflectionAttribute $attr) => $attr->newInstance(), $attributes);

        $this->attributeCache[$cacheKey] = $instances;
        return $instances;
    }

    /**
     * Get first attribute instance
     */
    public function getFirstAttribute(string|object $class, string $attributeClass, string $type = 'class', ?string $name = null): ?object
    {
        $attributes = match ($type) {
            'class' => $this->getClassAttributes($class, $attributeClass),
            'method' => $this->getMethodAttributes($class, $name ?? '', $attributeClass),
            'property' => $this->getPropertyAttributes($class, $name ?? '', $attributeClass),
            default => [],
        };

        return $attributes[0] ?? null;
    }

    /**
     * Check if class/method/property has attribute
     */
    public function hasAttribute(string|object $class, string $attributeClass, string $type = 'class', ?string $name = null): bool
    {
        return $this->getFirstAttribute($class, $attributeClass, $type, $name) !== null;
    }

    /**
     * Extract metadata from attributes
     */
    public function extractMetadata(string|object $class, ?string $method = null): array
    {
        $metadata = [];

        // Class attributes
        $classAttributes = $this->getClassAttributes($class);
        foreach ($classAttributes as $attribute) {
            $metadata['class'][get_class($attribute)] = $this->attributeToArray($attribute);
        }

        if ($method) {
            // Method attributes
            $methodAttributes = $this->getMethodAttributes($class, $method);
            foreach ($methodAttributes as $attribute) {
                $metadata['methods'][$method][get_class($attribute)] = $this->attributeToArray($attribute);
            }
        } else {
            // All methods
            $reflection = new ReflectionClass(is_object($class) ? get_class($class) : $class);
            foreach ($reflection->getMethods() as $methodReflection) {
                $methodAttributes = $this->getMethodAttributes($class, $methodReflection->getName());
                foreach ($methodAttributes as $attribute) {
                    $metadata['methods'][$methodReflection->getName()][get_class($attribute)] = $this->attributeToArray($attribute);
                }
            }
        }

        return $metadata;
    }

    /**
     * Convert attribute to array
     */
    protected function attributeToArray(object $attribute): array
    {
        $reflection = new ReflectionClass($attribute);
        $data = [];

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $data[$property->getName()] = $property->getValue($attribute);
        }

        return $data;
    }

    /**
     * Generate code from attributes
     */
    public function generateCode(string|object $class, string $template = 'default'): string
    {
        $metadata = $this->extractMetadata($class);
        
        return match ($template) {
            'routes' => $this->generateRoutes($metadata),
            'middleware' => $this->generateMiddleware($metadata),
            'validation' => $this->generateValidation($metadata),
            default => $this->generateDefault($metadata),
        };
    }

    /**
     * Generate routes from attributes
     */
    protected function generateRoutes(array $metadata): string
    {
        $code = "// Auto-generated routes from attributes\n";
        $code .= "use Core\Routing\Route;\n\n";

        foreach ($metadata['methods'] ?? [] as $method => $attributes) {
            foreach ($attributes as $attributeClass => $data) {
                if (str_contains($attributeClass, 'Route')) {
                    $uri = $data['uri'] ?? '';
                    $httpMethod = $data['method'] ?? 'GET';
                    $code .= "Route::{$httpMethod}('{$uri}', [Controller::class, '{$method}']);\n";
                }
            }
        }

        return $code;
    }

    /**
     * Generate middleware from attributes
     */
    protected function generateMiddleware(array $metadata): string
    {
        $code = "// Auto-generated middleware from attributes\n";

        foreach ($metadata['methods'] ?? [] as $method => $attributes) {
            foreach ($attributes as $attributeClass => $data) {
                if (str_contains($attributeClass, 'Middleware')) {
                    $middleware = $data['middleware'] ?? [];
                    if (is_array($middleware)) {
                        $code .= "// {$method}: " . implode(', ', $middleware) . "\n";
                    }
                }
            }
        }

        return $code;
    }

    /**
     * Generate validation rules from attributes
     */
    protected function generateValidation(array $metadata): string
    {
        $code = "// Auto-generated validation rules from attributes\n";

        foreach ($metadata['methods'] ?? [] as $method => $attributes) {
            foreach ($attributes as $attributeClass => $data) {
                if (str_contains($attributeClass, 'Validate') || str_contains($attributeClass, 'Rule')) {
                    $rules = $data['rules'] ?? [];
                    $code .= "// {$method}: " . json_encode($rules) . "\n";
                }
            }
        }

        return $code;
    }

    /**
     * Generate default code
     */
    protected function generateDefault(array $metadata): string
    {
        return "// Metadata:\n" . json_encode($metadata, JSON_PRETTY_PRINT);
    }

    /**
     * Clear attribute cache
     */
    public function clearCache(): void
    {
        $this->attributeCache = [];
    }

    /**
     * Get statistics
     */
    public function getStats(): array
    {
        return [
            'cached_attributes' => count($this->attributeCache),
        ];
    }
}
