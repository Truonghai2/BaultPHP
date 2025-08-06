<?php

namespace Core\CQRS;

use Core\Application;

class SimpleCommandBus implements CommandBus
{
    /**
     * @var array<string, string>
     */
    private array $handlers = [];

    public function __construct(private readonly Application $app)
    {
    }

    public function register(string $commandClass, string $handlerClass): void
    {
        $this->handlers[$commandClass] = $handlerClass;
    }

    public function map(array $map): void
    {
        foreach ($map as $commandClass => $handlerClass) {
            $this->register($commandClass, $handlerClass);
        }
    }

    public function dispatch(Command $command)
    {
        $commandClass = get_class($command);
        $handlerClass = $this->findHandler($commandClass);

        $handler = $this->app->make($handlerClass);

        return $handler->handle($command);
    }

    private function findHandler(string $commandClass): string
    {
        // 1. Check for an explicitly registered handler.
        if (isset($this->handlers[$commandClass])) {
            return $this->handlers[$commandClass];
        }

        // 2. If not found, try to resolve by convention (e.g., App\Commands\MyCommand -> App\Commands\MyCommandHandler).
        $handlerClass = $commandClass . 'Handler';
        if (class_exists($handlerClass)) {
            return $handlerClass;
        }

        throw new \InvalidArgumentException("No handler registered for command [{$commandClass}]. Also, could not find a handler by convention: [{$handlerClass}] was not found.");
    }
}
