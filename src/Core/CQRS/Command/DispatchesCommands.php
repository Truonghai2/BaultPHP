<?php

namespace Core\CQRS\Command;

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
    /**
     * Dispatch a command using the CommandBus.
     *
     * This method resolves the CommandBus from the service container and
     * dispatches the provided command.
     *
     * @param Command $command The command to be dispatched.
     * @return mixed The result of the command execution.
     */
    protected function dispatch(Command $command): mixed
    {
        return app(CommandBus::class)->dispatch($command);
    }
}
