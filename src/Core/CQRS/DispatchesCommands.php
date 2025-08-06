<?php

namespace Core\CQRS;

/**
 * Provides a convenient way to dispatch commands.
 *
 * Any class that uses this trait will have access to the `dispatch` method,
 * which resolves the CommandBus from the service container and dispatches
 * the given command. This abstracts away the need for direct dependency injection
 * of the CommandBus in constructors.
 */
trait DispatchesCommands
{
    protected function dispatch(Command $command): mixed
    {
        return app(CommandBus::class)->dispatch($command);
    }
}
