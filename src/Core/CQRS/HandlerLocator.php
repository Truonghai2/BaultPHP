<?php

declare(strict_types=1);

namespace Core\CQRS;

use InvalidArgumentException;

/**
 * A trait for locating handlers for commands or queries.
 * It supports both explicit registration and convention-based resolution.
 */
trait HandlerLocator
{
    /**
     * @var array<string, string>
     */
    private array $handlers = [];

    /**
     * Find the handler for a given class name (command or query).
     *
     * @param string $className The FQCN of the command or query.
     * @param string $type The type of object being handled ('command' or 'query').
     * @return string The FQCN of the handler.
     * @throws InvalidArgumentException If no handler is found.
     */
    private function findHandler(string $className, string $type): string
    {
        if (isset($this->handlers[$className])) {
            return $this->handlers[$className];
        }

        $handlerClass = $className . 'Handler';
        if (class_exists($handlerClass)) {
            return $handlerClass;
        }

        throw new InvalidArgumentException("No handler registered for {$type} [{$className}]. Also, could not find a handler by convention: [{$handlerClass}] was not found.");
    }
}
