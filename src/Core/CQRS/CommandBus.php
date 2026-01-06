<?php

namespace Core\CQRS;

use Core\CQRS\Contracts\CommandHandlerInterface;
use Core\CQRS\Contracts\CommandInterface;
use Core\CQRS\Middleware\MiddlewareInterface;

/**
 * Command Bus
 * 
 * Dispatches commands to their handlers.
 * Supports middleware for cross-cutting concerns.
 */
class CommandBus
{
    /**
     * @var array<string, string> Command => Handler mapping
     */
    private array $handlers = [];

    /**
     * @var MiddlewareInterface[]
     */
    private array $middleware = [];

    /**
     * Register a command handler
     */
    public function register(string $commandClass, string $handlerClass): void
    {
        $this->handlers[$commandClass] = $handlerClass;
    }

    /**
     * Add middleware to the pipeline
     */
    public function addMiddleware(MiddlewareInterface $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Dispatch a command
     */
    public function dispatch(CommandInterface $command): mixed
    {
        $commandClass = get_class($command);

        if (!isset($this->handlers[$commandClass])) {
            throw new \RuntimeException("No handler registered for command: {$commandClass}");
        }

        $handlerClass = $this->handlers[$commandClass];
        $handler = app($handlerClass);

        if (!$handler instanceof CommandHandlerInterface) {
            throw new \RuntimeException("Handler must implement CommandHandlerInterface");
        }

        // Build middleware pipeline
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn($next, $middleware) => fn($cmd) => $middleware->handle($cmd, $next),
            fn($cmd) => $handler->handle($cmd)
        );

        return $pipeline($command);
    }
}

