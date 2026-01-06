<?php

namespace Core\CQRS\Exceptions;

use InvalidArgumentException;

/**
 * Exception thrown when a handler cannot be found for a command or query.
 */
class HandlerNotFoundException extends InvalidArgumentException
{
    public function __construct(
        string $message,
        private readonly string $commandOrQueryClass,
        private readonly string $type,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getCommandOrQueryClass(): string
    {
        return $this->commandOrQueryClass;
    }

    public function getType(): string
    {
        return $this->type;
    }
}

