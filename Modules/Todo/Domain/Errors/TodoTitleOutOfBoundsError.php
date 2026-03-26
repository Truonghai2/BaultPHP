<?php

namespace Modules\Todo\Domain\Errors;

use Core\Domain\DomainError;

/**
 * Error: Todo title is out of valid bounds.
 */
class TodoTitleOutOfBoundsError extends DomainError
{
    public function __construct(int $length, int $min = 3, int $max = 200)
    {
        parent::__construct(
            message: "Todo title length ({$length}) must be between {$min} and {$max} characters",
            errorCode: 'todo_title_out_of_bounds',
            context: [
                'length' => $length,
                'min' => $min,
                'max' => $max,
            ]
        );
    }
}
