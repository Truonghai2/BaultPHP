<?php

namespace Core\CQRS\Command\Implementation;

use Core\Application;
use Core\CQRS\Command\Command;
use Core\CQRS\Command\CommandBus;
use Core\CQRS\HandlerLocator;

/**
 * SimpleCommandBus is a basic implementation of a command bus that maps commands to their handlers.
 * It allows for registering command-handler pairs and dispatching commands to the appropriate handler.
 * 
 * Optimized with handler caching and improved error handling.
 */
class SimpleCommandBus implements CommandBus
{
    use HandlerLocator;

    /**
     * @var array<string, object> Cache for resolved handler instances
     */
    private array $handlerInstances = [];

    /**
     * SimpleCommandBus constructor.
     *
     * @param Application $app The application container to resolve handlers.
     */
    public function __construct(private readonly Application $app)
    {
    }

    /**
     * Register a command handler
     *
     * @param string $commandClass
     * @param string $handlerClass
     * @return void
     */
    public function register(string $commandClass, string $handlerClass): void
    {
        $this->handlers[$commandClass] = $handlerClass;
        // Clear instance cache when registering new handlers
        unset($this->handlerInstances[$commandClass]);
    }

    /**
     * Map multiple command-handler pairs.
     *
     * @param array<string, string> $map
     * @return void
     */
    public function map(array $map): void
    {
        foreach ($map as $commandClass => $handlerClass) {
            $this->register($commandClass, $handlerClass);
        }
    }

    /**
     * Dispatch the command to its handler.
     *
     * @param Command $command
     * @return mixed
     * @throws \InvalidArgumentException If no handler is registered for the command.
     */
    public function dispatch(Command $command): mixed
    {
        $commandClass = get_class($command);
        $handlerClass = $this->findHandler($commandClass, 'command');

        // Cache handler instances for better performance
        if (!isset($this->handlerInstances[$handlerClass])) {
            $this->handlerInstances[$handlerClass] = $this->app->make($handlerClass);
        }

        $handler = $this->handlerInstances[$handlerClass];

        return $handler->handle($command);
    }

    /**
     * Clear all cached handler instances (useful for testing).
     */
    public function clearCache(): void
    {
        $this->handlerInstances = [];
        $this->clearHandlerCache();
    }
}
