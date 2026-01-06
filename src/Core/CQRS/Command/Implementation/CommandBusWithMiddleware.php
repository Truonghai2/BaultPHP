<?php

namespace Core\CQRS\Command\Implementation;

use Core\Application;
use Core\CQRS\Command\Command;
use Core\CQRS\Command\CommandBus;
use Core\CQRS\Middleware\CommandMiddleware;

/**
 * CommandBusWithMiddleware is a command bus that applies middleware to commands before dispatching them.
 * It allows for additional processing, such as logging or transaction management, around command execution.
 * 
 * Optimized with middleware instance caching.
 */
class CommandBusWithMiddleware implements CommandBus
{
    /**
     * @var array<string, CommandMiddleware> Cache for resolved middleware instances
     */
    private array $middlewareInstances = [];

    /**
     * @param SimpleCommandBus $decoratedBus The inner command bus that will ultimately execute the handler.
     * @param array<class-string<CommandMiddleware>> $middleware The array of middleware classes to apply.
     * @param Application $app The application container to resolve middleware instances.
     */
    public function __construct(
        private readonly SimpleCommandBus $decoratedBus,
        private readonly array $middleware,
        private readonly Application $app,
    ) {
    }

    /**
     * Delegate handler registration to the inner bus.
     *
     * @param string $commandClass
     * @param string $handlerClass
     * @return void
     */
    public function register(string $commandClass, string $handlerClass): void
    {
        $this->decoratedBus->register($commandClass, $handlerClass);
    }

    /**
     * Delegate handler mapping to the inner bus.
     *
     * @param array<string, string> $map
     * @return void
     */
    public function map(array $map): void
    {
        $this->decoratedBus->map($map);
    }

    /**
     * Dispatch the command through the middleware pipeline.
     *
     * @param Command $command
     * @return mixed
     */
    public function dispatch(Command $command): mixed
    {
        if (empty($this->middleware)) {
            return $this->decoratedBus->dispatch($command);
        }

        $coreDispatch = fn (Command $c) => $this->decoratedBus->dispatch($c);

        $pipeline = array_reduce(
            array_reverse($this->middleware),
            $this->createNext(),
            $coreDispatch,
        );

        return $pipeline($command);
    }

    /**
     * Create a callable that wraps the next middleware in the pipeline.
     * 
     * Optimized with middleware instance caching.
     *
     * @return callable
     */
    private function createNext(): callable
    {
        return function (callable $stack, string $pipe) {
            return function (Command $command) use ($stack, $pipe) {
                // Cache middleware instances for better performance
                if (!isset($this->middlewareInstances[$pipe])) {
                    $this->middlewareInstances[$pipe] = $this->app->make($pipe);
                }

                return $this->middlewareInstances[$pipe]->handle($command, $stack);
            };
        };
    }

    /**
     * Clear all cached middleware instances (useful for testing).
     */
    public function clearCache(): void
    {
        $this->middlewareInstances = [];
        $this->decoratedBus->clearCache();
    }
}
