<?php

namespace Core\CQRS;

/**
 * Dispatches a command to its corresponding handler.
 */
interface CommandBus
{
    /**
     * Register a command-to-handler mapping.
     *
     * @param string $commandClass The fully qualified class name of the command.
     * @param string $handlerClass The fully qualified class name of the handler.
     */
    public function register(string $commandClass, string $handlerClass): void;

    /**
     * Register a map of commands to their handlers.
     *
     * @param array<class-string<Command>, class-string<CommandHandler>> $map
     */
    public function map(array $map): void;

    /**
     * Dispatch a command to its handler.
     */
    public function dispatch(Command $command);
}
