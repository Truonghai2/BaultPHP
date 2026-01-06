<?php

declare(strict_types=1);

namespace Core\CQRS;

use Core\CQRS\Exceptions\HandlerNotFoundException;
use InvalidArgumentException;

/**
 * A trait for locating handlers for commands or queries.
 * It supports both explicit registration and convention-based resolution.
 * 
 * Optimized with caching to avoid repeated class_exists() calls.
 */
trait HandlerLocator
{
    /**
     * @var array<string, string> Explicit handler registrations
     */
    private array $handlers = [];

    /**
     * @var array<string, string> Cache for convention-based handler lookups
     */
    private array $handlerCache = [];

    /**
     * @var array<string, bool> Cache for negative lookups (handler doesn't exist)
     */
    private array $negativeCache = [];

    /**
     * Find the handler for a given class name (command or query).
     *
     * @param string $className The FQCN of the command or query.
     * @param string $type The type of object being handled ('command' or 'query').
     * @return string The FQCN of the handler.
     * @throws HandlerNotFoundException If no handler is found.
     */
    private function findHandler(string $className, string $type): string
    {
        // Check explicit registration first
        if (isset($this->handlers[$className])) {
            return $this->handlers[$className];
        }

        // Check cache for convention-based lookup
        if (isset($this->handlerCache[$className])) {
            return $this->handlerCache[$className];
        }

        // Check negative cache to avoid repeated class_exists() calls
        if (isset($this->negativeCache[$className])) {
            $handlerClass = $className . 'Handler';
            throw new HandlerNotFoundException(
                "No handler registered for {$type} [{$className}]. " .
                "Also, could not find a handler by convention: [{$handlerClass}] was not found.",
                $className,
                $type
            );
        }

        // Try convention-based resolution
        $handlerClass = $className . 'Handler';
        
        if (class_exists($handlerClass)) {
            // Cache the result for future lookups
            $this->handlerCache[$className] = $handlerClass;
            return $handlerClass;
        }

        // Cache negative result
        $this->negativeCache[$className] = true;

        throw new HandlerNotFoundException(
            "No handler registered for {$type} [{$className}]. " .
            "Also, could not find a handler by convention: [{$handlerClass}] was not found.",
            $className,
            $type
        );
    }

    /**
     * Clear the handler cache (useful for testing or hot-reloading).
     */
    protected function clearHandlerCache(): void
    {
        $this->handlerCache = [];
        $this->negativeCache = [];
    }

    /**
     * Check if a handler is registered or can be found by convention.
     */
    protected function hasHandler(string $className): bool
    {
        if (isset($this->handlers[$className])) {
            return true;
        }

        if (isset($this->handlerCache[$className])) {
            return true;
        }

        if (isset($this->negativeCache[$className])) {
            return false;
        }

        $handlerClass = $className . 'Handler';
        if (class_exists($handlerClass)) {
            $this->handlerCache[$className] = $handlerClass;
            return true;
        }

        $this->negativeCache[$className] = true;
        return false;
    }
}
