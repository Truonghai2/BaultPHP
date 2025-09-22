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

        $classPrefix = '';
        $classMiddleware = [];
        $classNamePrefix = '';
        $classGroup = null;

        if (!empty($classAttributes)) {
            /** @var RouteAttribute $classRouteAttribute */
            $classRouteAttribute = $classAttributes[0]->newInstance();
            $classPrefix = $classRouteAttribute->prefix ?? $classRouteAttribute->uri ?? '';
            $classMiddleware = (array) ($classRouteAttribute->middleware ?? []);
            $classNamePrefix = $classRouteAttribute->name ?? '';
            $classGroup = $classRouteAttribute->group ?? null;
        }

        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $methodAttributes = $method->getAttributes(RouteAttribute::class);

            if (empty($methodAttributes)) {
                continue;
            }

            foreach ($methodAttributes as $methodAttribute) {
                /** @var RouteAttribute $methodRouteAttribute */
                $methodRouteAttribute = $methodAttribute->newInstance();

                $uri = rtrim($classPrefix, '/') . '/' . ltrim($methodRouteAttribute->uri, '/');
                $uri = ($uri !== '/') ? rtrim($uri, '/') : '/';
                if (empty($uri)) {
                    $uri = '/';
                }

                $middleware = array_merge($classMiddleware, (array) $methodRouteAttribute->middleware);

                $name = $methodRouteAttribute->name;
                if ($name && $classNamePrefix) {
                    $name = rtrim($classNamePrefix, '.') . '.' . ltrim($name, '.');
                }

                $route = $router->addRoute($methodRouteAttribute->method, $uri, [$className, $method->getName()]);

                if ($name) {
                    $route->name($name);
                }
                if (!empty($middleware)) {
                    $route->middleware($middleware);
                }

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
    // This method uses tokenization, which is faster than `require` or `include`,
    // to extract class and namespace information without executing the file's code.
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

            if ($token[0] === T_NAMESPACE) {
                $namespace = '';
                for ($j = $i + 2; $j < count($tokens); $j++) {
                    if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_STRING, T_NAME_QUALIFIED])) {
                        $namespace .= $tokens[$j][1];
                    } elseif ($tokens[$j] === '{' || $tokens[$j] === ';') {
                        break;
                    }
                }
                $namespaceFound = true;
            }

            if ($token[0] === T_CLASS && isset($tokens[$i + 2]) && is_array($tokens[$i + 2]) && $tokens[$i + 2][0] === T_STRING) {
                $className = $tokens[$i + 2][1];
                $classes[] = $namespaceFound ? $namespace . '\\' . $className : $className;
            }
        }
        return $classes;
    }
}
