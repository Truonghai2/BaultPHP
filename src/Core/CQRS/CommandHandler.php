<?php

namespace Core\CQRS;

/**
 * Interface for a class that handles a specific command.
 */
interface CommandHandler
{
    /**
     * Handle the given command.
     *
     * @param Command $command The command to be handled.
     * @return mixed The result of the command handling, if any.
     */
    public function handle(Command $command): mixed;
}
