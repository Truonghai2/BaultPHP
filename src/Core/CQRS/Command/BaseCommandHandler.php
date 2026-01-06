<?php

namespace Core\CQRS\Command;

use Core\CQRS\Command\Command;

/**
 * Base Command Handler
 * 
 * Abstract base class for command handlers with type safety.
 * Extend this class and specify the command type in the handle method.
 * 
 * @template TCommand of Command
 */
abstract class BaseCommandHandler implements CommandHandler
{
    /**
     * Handle the command
     * 
     * @param TCommand $command
     * @return mixed
     */
    abstract public function handle(Command $command): mixed;
}

