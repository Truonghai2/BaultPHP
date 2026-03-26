<?php

declare(strict_types=1);

namespace Core\Attributes;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Attribute Code Generator
 *
 * Generate code từ attributes for:
 * - Route registration
 * - Middleware configuration
 * - Validation rules
 * - API documentation
 *
 * Features:
 * - Code generation từ attributes
 * - Route generation
 * - Middleware generation
 * - Validation generation
 */
class AttributeCodeGenerator
{
    public function __construct(
        protected AttributeEnhancer $enhancer,
    ) {
    }

    /**
     * Generate route registration code
     */
    public function generateRoutes(string|object $class): string
    {
        $className = is_object($class) ? get_class($class) : $class;
        $reflection = new ReflectionClass($className);
        
        $code = "// Auto-generated routes for {$className}\n";
        $code .= "use Core\Routing\Route;\n\n";

        // Class-level route prefix
        $classAttributes = $this->enhancer->getClassAttributes($class);
        $prefix = '';
        foreach ($classAttributes as $attr) {
            if (method_exists($attr, 'prefix') && $attr->prefix) {
                $prefix = $attr->prefix;
                break;
            }
        }

        // Method-level routes
        foreach ($reflection->getMethods() as $method) {
            $methodAttributes = $this->enhancer->getMethodAttributes($class, $method->getName());
            
            foreach ($methodAttributes as $attr) {
                if (method_exists($attr, 'uri') && method_exists($attr, 'method')) {
                    $uri = $prefix . $attr->uri;
                    $httpMethod = $attr->method ?? 'GET';
                    $routeName = $attr->name ?? null;
                    $middleware = $attr->middleware ?? [];

                    $code .= "Route::{$httpMethod}('{$uri}', [{$className}::class, '{$method->getName()}']";
                    
                    if ($routeName) {
                        $code .= "->name('{$routeName}')";
                    }
                    
                    if (!empty($middleware)) {
                        $middlewareStr = is_array($middleware) 
                            ? "['" . implode("', '", $middleware) . "']"
                            : "'{$middleware}'";
                        $code .= "->middleware({$middlewareStr})";
                    }
                    
                    $code .= ");\n";
                }
            }
        }

        return $code;
    }

    /**
     * Generate middleware configuration
     */
    public function generateMiddlewareConfig(string|object $class): string
    {
        $className = is_object($class) ? get_class($class) : $class;
        $reflection = new ReflectionClass($className);
        
        $code = "// Auto-generated middleware configuration for {$className}\n";

        foreach ($reflection->getMethods() as $method) {
            $methodAttributes = $this->enhancer->getMethodAttributes($class, $method->getName());
            
            foreach ($methodAttributes as $attr) {
                if (method_exists($attr, 'middleware')) {
                    $middleware = $attr->middleware ?? [];
                    if (!empty($middleware)) {
                        $middlewareStr = is_array($middleware) 
                            ? implode(', ', $middleware)
                            : $middleware;
                        $code .= "// {$method->getName()}: {$middlewareStr}\n";
                    }
                }
            }
        }

        return $code;
    }

    /**
     * Generate validation rules
     */
    public function generateValidationRules(string|object $class): string
    {
        $className = is_object($class) ? get_class($class) : $class;
        $reflection = new ReflectionClass($className);
        
        $code = "// Auto-generated validation rules for {$className}\n";

        foreach ($reflection->getMethods() as $method) {
            $methodAttributes = $this->enhancer->getMethodAttributes($class, $method->getName());
            
            foreach ($methodAttributes as $attr) {
                if (method_exists($attr, 'rules')) {
                    $rules = $attr->rules ?? [];
                    if (!empty($rules)) {
                        $code .= "// {$method->getName()}:\n";
                        $code .= json_encode($rules, JSON_PRETTY_PRINT) . "\n\n";
                    }
                }
            }
        }

        return $code;
    }

    /**
     * Generate API documentation
     */
    public function generateApiDoc(string|object $class): string
    {
        $className = is_object($class) ? get_class($class) : $class;
        $reflection = new ReflectionClass($className);
        
        $doc = "# API Documentation for {$className}\n\n";

        foreach ($reflection->getMethods() as $method) {
            $methodAttributes = $this->enhancer->getMethodAttributes($class, $method->getName());
            
            foreach ($methodAttributes as $attr) {
                if (method_exists($attr, 'uri') && method_exists($attr, 'method')) {
                    $uri = $attr->uri ?? '';
                    $httpMethod = $attr->method ?? 'GET';
                    
                    $doc .= "## {$httpMethod} {$uri}\n\n";
                    $doc .= "**Method:** `{$method->getName()}`\n\n";
                    
                    if (method_exists($attr, 'name') && $attr->name) {
                        $doc .= "**Route Name:** `{$attr->name}`\n\n";
                    }
                    
                    if (method_exists($attr, 'middleware')) {
                        $middleware = $attr->middleware ?? [];
                        if (!empty($middleware)) {
                            $doc .= "**Middleware:** " . (is_array($middleware) ? implode(', ', $middleware) : $middleware) . "\n\n";
                        }
                    }
                    
                    $doc .= "---\n\n";
                }
            }
        }

        return $doc;
    }

    /**
     * Generate OpenAPI/Swagger specification
     */
    public function generateOpenApiSpec(string|object $class): array
    {
        $className = is_object($class) ? get_class($class) : $class;
        $reflection = new ReflectionClass($className);
        
        $spec = [
            'paths' => [],
        ];

        foreach ($reflection->getMethods() as $method) {
            $methodAttributes = $this->enhancer->getMethodAttributes($class, $method->getName());
            
            foreach ($methodAttributes as $attr) {
                if (method_exists($attr, 'uri') && method_exists($attr, 'method')) {
                    $uri = $attr->uri ?? '';
                    $httpMethod = strtolower($attr->method ?? 'GET');
                    
                    if (!isset($spec['paths'][$uri])) {
                        $spec['paths'][$uri] = [];
                    }
                    
                    $spec['paths'][$uri][$httpMethod] = [
                        'summary' => $method->getName(),
                        'operationId' => $method->getName(),
                        'tags' => [$className],
                    ];
                    
                    if (method_exists($attr, 'name') && $attr->name) {
                        $spec['paths'][$uri][$httpMethod]['operationId'] = $attr->name;
                    }
                }
            }
        }

        return $spec;
    }
}
