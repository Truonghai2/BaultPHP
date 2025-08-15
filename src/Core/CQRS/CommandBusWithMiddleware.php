<?php

namespace Core\CQRS;

use Core\Application;

/**
 * CommandBusWithMiddleware is a command bus that applies middleware to commands before dispatching them.
 * It allows for additional processing, such as logging or transaction management, around command execution.
 */
class CommandBusWithMiddleware implements CommandBus
{
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
     * @param array $map
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
     * @return void
     */
    public function dispatch(Command $command)
    {
        // The final step in the pipeline is to dispatch the command using the decorated bus.
        $coreDispatch = fn (Command $c) => $this->decoratedBus->dispatch($c);

        // Build the middleware pipeline by wrapping each middleware around the next.
        // We reverse the array to build the pipeline from the inside out.
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
     * @return callable
     */
    private function createNext(): callable
    {
        return function (callable $stack, string $pipe) {
            return function (Command $command) use ($stack, $pipe) {
                return $this->app->make($pipe)->handle($command, $stack);
            };
        };
    }
}
