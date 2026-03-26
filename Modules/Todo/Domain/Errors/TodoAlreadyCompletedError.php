<?php

namespace Modules\Todo\Domain\Errors;

use Core\Domain\DomainError;

/**
 * Error: Todo is already completed.
 */
class TodoAlreadyCompletedError extends DomainError
{
    public function __construct(string $todoId)
    {
        parent::__construct(
            message: "Todo with ID '{$todoId}' is already completed",
            errorCode: 'todo_already_completed',
            context: ['todo_id' => $todoId]
        );
    }
}
