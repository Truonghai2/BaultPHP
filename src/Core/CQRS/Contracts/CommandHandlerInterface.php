<?php

namespace Core\CQRS\Contracts;

/**
 * Command Handler Interface
 */
interface CommandHandlerInterface
{
    /**
     * Handle the command
     */
    public function handle(CommandInterface $command): mixed;
}

