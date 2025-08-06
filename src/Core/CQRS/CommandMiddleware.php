<?php

namespace Core\CQRS;

/**
 * Defines the contract for a middleware that can process a command
 * before it reaches its handler.
 */
interface CommandMiddleware
{
    /**
     * @param Command $command The command being dispatched.
     * @param callable $next The next middleware in the chain.
     */
    public function handle(Command $command, callable $next);
}
