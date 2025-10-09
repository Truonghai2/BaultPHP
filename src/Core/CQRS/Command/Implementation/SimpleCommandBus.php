<?php

namespace Core\CQRS\Command\Implementation;

use Core\Application;
use Core\CQRS\Command\Command;
use Core\CQRS\Command\CommandBus;
use Core\CQRS\HandlerLocator;

/**
 * SimpleCommandBus is a basic implementation of a command bus that maps commands to their handlers.
 * It allows for registering command-handler pairs and dispatching commands to the appropriate handler.
 */
class SimpleCommandBus implements CommandBus
{
    use HandlerLocator;

    /**
     * SimpleCommandBus constructor.
     *
     * @param Application $app The application container to resolve handlers.
     */
    public function __construct(private readonly Application $app)
    {
    }

    /**
     * Dispatch the command through the middleware pipeline.
     *
     * @param string $commandClass
     * @param string $handlerClass
     * @return void
     */
    public function register(string $commandClass, string $handlerClass): void
    {
        $this->handlers[$commandClass] = $handlerClass;
    }

    /**
     * Map multiple command-handler pairs.
     *
     * @param array $map
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

        $handler = $this->app->make($handlerClass);

        return $handler->handle($command);
    }
}
