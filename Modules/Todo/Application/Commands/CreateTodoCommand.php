<?php

namespace Modules\Todo\Application\Commands;

use Core\CQRS\Command;

/**
 * Command: Create a new Todo.
 */
class CreateTodoCommand extends Command
{
    public function __construct(
        public readonly string $title,
        public readonly string $userId
    ) {
        parent::__construct('Todo');
    }
}
