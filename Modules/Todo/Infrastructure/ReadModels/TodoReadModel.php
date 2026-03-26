<?php

namespace Modules\Todo\Infrastructure\ReadModels;

/**
 * Todo Read Model.
 * 
 * Optimized flat structure for queries.
 * Denormalized and cached for fast reads.
 */
class TodoReadModel
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $userId,
        public readonly bool $completed,
        public readonly string $createdAt,
        public readonly ?string $completedAt = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            title: $data['title'],
            userId: $data['user_id'],
            completed: (bool) $data['completed'],
            createdAt: $data['created_at'],
            completedAt: $data['completed_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'user_id' => $this->userId,
            'completed' => $this->completed,
            'created_at' => $this->createdAt,
            'completed_at' => $this->completedAt,
        ];
    }
}
