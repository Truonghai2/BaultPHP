<?php

namespace Core\Routing;

use Core\Routing\Attributes\Route as RouteAttribute;
use ReflectionClass;
use ReflectionMethod;

/**
 * Scans directories for controllers and registers their routes based on attributes.
 * This class is responsible for handling both class-level and method-level route attributes,
 * combining prefixes and middleware as needed.
 */
class RouteRegistrar
{
    /**
     * Register all routes found in the given paths.
     *
     * @param Router $router The main router instance.
     * @param array $paths An array of absolute paths to scan for controllers.
     */
    public function registerRoutes(Router $router, array $paths): void
    {
        $files = $this->getPhpFilesFromPaths($paths);
        foreach ($files as $file) {
            $classes = $this->getClassesFromFile($file->getPathname());
            foreach ($classes as $class) {
                $this->registerRoutesFromClass($router, $class);
            }
        }
    }

    /**
     * Registers all routes defined within a single class.
     *
     * @param Router $router
     * @param string $className The fully qualified name of the class.
     */
    protected function registerRoutesFromClass(Router $router, string $className): void
    {
        if (!class_exists($className)) {
            return;
        }

        $reflectionClass = new ReflectionClass($className);
        $classAttributes = $reflectionClass->getAttributes(RouteAttribute::class);

        // Get prefix, middleware, name prefix, and group from class-level attribute
        $classPrefix = '';
        $classMiddleware = [];
        $classNamePrefix = '';
        $classGroup = null;

        if (!empty($classAttributes)) {
            /** @var RouteAttribute $classRouteAttribute */
            $classRouteAttribute = $classAttributes[0]->newInstance();
            // A class-level attribute can define a prefix using either 'prefix' or 'uri'
            $classPrefix = $classRouteAttribute->prefix ?? $classRouteAttribute->uri ?? '';
            $classMiddleware = (array) ($classRouteAttribute->middleware ?? []);
            // A class-level name can be used as a prefix for method-level names
            $classNamePrefix = $classRouteAttribute->name ?? '';
            $classGroup = $classRouteAttribute->group ?? null;
        }

        // Scan all public methods for route attributes
        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // A method can have multiple Route attributes (IS_REPEATABLE)
            $methodAttributes = $method->getAttributes(RouteAttribute::class);

            if (empty($methodAttributes)) {
                continue;
            }

            foreach ($methodAttributes as $methodAttribute) {
                /** @var RouteAttribute $methodRouteAttribute */
                $methodRouteAttribute = $methodAttribute->newInstance();

                // 1. Combine URI
                // Ensure there's exactly one slash between prefix and uri
                $uri = rtrim($classPrefix, '/') . '/' . ltrim($methodRouteAttribute->uri, '/');
                // Avoid trailing slash on the final URI, except for the root '/'
                $uri = ($uri !== '/') ? rtrim($uri, '/') : '/';
                if (empty($uri)) {
                    $uri = '/'; // Handle case where both are empty strings
                }

                // 2. Combine Middleware
                $middleware = array_merge($classMiddleware, (array) $methodRouteAttribute->middleware);

                // 3. Combine Name
                $name = $methodRouteAttribute->name;
                if ($name && $classNamePrefix) {
                    // If class has a name "auth." and method has "login", result is "auth.login"
                    $name = rtrim($classNamePrefix, '.') . '.' . ltrim($name, '.');
                }

                // Add the route to the router
                $route = $router->addRoute($methodRouteAttribute->method, $uri, [$className, $method->getName()]);

                if ($name) {
                    $route->name($name);
                }
                if (!empty($middleware)) {
                    $route->middleware($middleware);
                }

                // 4. Apply Group (method group overrides class group)
                $group = $methodRouteAttribute->group ?? $classGroup;
                if ($group) {
                    $route->group($group);
                }
            }
        }
    }

    /**
     * Recursively finds all PHP files in the given directories.
     */
    protected function getPhpFilesFromPaths(array $paths): \Iterator
    {
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!$file->isDir() && $file->getExtension() === 'php') {
                    yield $file;
                }
            }
        }
    }

    /**
     * Parses a PHP file to get the fully qualified class name without including the file.
     */
    protected function getClassesFromFile(string $filePath): array
    {
        $tokens = token_get_all(file_get_contents($filePath));
        $namespace = '';
        $classes = [];
        $namespaceFound = false;

        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                continue;
            }

            // Find the namespace
            if ($token[0] === T_NAMESPACE) {
                $namespace = '';
                // Look ahead to find the namespace name
                for ($j = $i + 2; $j < count($tokens); $j++) {
                    if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_STRING, T_NAME_QUALIFIED])) {
                        $namespace .= $tokens[$j][1];
                    } elseif ($tokens[$j] === '{' || $tokens[$j] === ';') {
                        break;
                    }
                }
                $namespaceFound = true;
            }

            // Find the class definition
            if ($token[0] === T_CLASS && isset($tokens[$i + 2]) && is_array($tokens[$i + 2]) && $tokens[$i + 2][0] === T_STRING) {
                $className = $tokens[$i + 2][1];
                $classes[] = $namespaceFound ? $namespace . '\\' . $className : $className;
            }
        }
        return $classes;
    }
}
