<?php

namespace Core\CQRS\Command;

/**
 * Interface for a class that handles a specific command.
 * 
 * @template TCommand of Command
 */
interface CommandHandler
{
    /**
     * Handle the given command.
     *
     * @param TCommand $command The command to be handled.
     * @return mixed The result of the command handling, if any.
     */
    public function handle(Command $command): mixed;
}
