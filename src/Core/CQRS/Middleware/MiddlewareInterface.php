<?php

namespace Core\CQRS\Middleware;

use Core\CQRS\Contracts\CommandInterface;
use Core\CQRS\Contracts\QueryInterface;

/**
 * CQRS Middleware Interface
 */
interface MiddlewareInterface
{
    /**
     * Handle the message and pass to next middleware
     */
    public function handle(CommandInterface|QueryInterface $message, callable $next): mixed;
}

