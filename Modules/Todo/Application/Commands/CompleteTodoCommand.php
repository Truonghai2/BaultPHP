<?php

namespace Modules\Todo\Application\Commands;

use Core\CQRS\Command;

class CompleteTodoCommand extends Command
{
    public function __construct(
        public readonly string $todoId
    ) {
        parent::__construct('Todo');
    }
}
