<?php

namespace Modules\Todo\Domain\Events;

class TodoUncompleted
{
    public function __construct(
        public readonly string $todoId,
        public readonly int $occurredAt = 0
    ) {
        if ($this->occurredAt === 0) {
            $this->occurredAt = time();
        }
    }

    public function eventName(): string
    {
        return 'todo.uncompleted';
    }

    public function toArray(): array
    {
        return [
            'todo_id' => $this->todoId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
