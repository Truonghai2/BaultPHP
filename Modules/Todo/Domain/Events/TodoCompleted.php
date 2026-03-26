<?php

namespace Modules\Todo\Domain\Events;

class TodoCompleted
{
    public function __construct(
        public readonly string $todoId,
        public readonly int $completedAt,
        public readonly int $occurredAt = 0
    ) {
        if ($this->occurredAt === 0) {
            $this->occurredAt = time();
        }
    }

    public function eventName(): string
    {
        return 'todo.completed';
    }

    public function toArray(): array
    {
        return [
            'todo_id' => $this->todoId,
            'completed_at' => $this->completedAt,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
