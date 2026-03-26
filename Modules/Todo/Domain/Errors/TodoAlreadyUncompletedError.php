<?php

namespace Modules\Todo\Domain\Errors;

use Core\Domain\DomainError;

/**
 * Error: Todo is already uncompleted.
 */
class TodoAlreadyUncompletedError extends DomainError
{
    public function __construct(string $todoId)
    {
        parent::__construct(
            message: "Todo with ID '{$todoId}' is not completed yet",
            errorCode: 'todo_already_uncompleted',
            context: ['todo_id' => $todoId]
        );
    }
}
