<?php

namespace Modules\Todo\Domain\Events;

/**
 * Domain Event: Todo was created.
 */
class TodoCreated
{
    public function __construct(
        public readonly string $todoId,
        public readonly string $title,
        public readonly string $userId,
        public readonly int $createdAt,
        public readonly int $occurredAt = 0
    ) {
        if ($this->occurredAt === 0) {
            $this->occurredAt = time();
        }
    }

    public function eventName(): string
    {
        return 'todo.created';
    }

    public function toArray(): array
    {
        return [
            'todo_id' => $this->todoId,
            'title' => $this->title,
            'user_id' => $this->userId,
            'created_at' => $this->createdAt,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
