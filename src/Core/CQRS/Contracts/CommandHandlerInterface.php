<?php

namespace Core\CQRS\Contracts;

/**
 * Command Handler Interface
 * 
 * Generic interface for command handlers.
 * Handlers should implement this interface with specific command types.
 * 
 * @template TCommand of CommandInterface
 */
interface CommandHandlerInterface
{
    /**
     * Handle the command
     * 
     * @param TCommand $command
     * @return mixed
     */
    public function handle(CommandInterface $command): mixed;
}
