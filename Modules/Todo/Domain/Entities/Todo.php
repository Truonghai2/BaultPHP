<?php

namespace Modules\Todo\Domain\Entities;

use Modules\Todo\Domain\ValueObjects\TodoTitle;
use Modules\Todo\Domain\Events\{TodoCreated, TodoCompleted, TodoUncompleted};
use Modules\Todo\Domain\Rules\{TodoAlreadyCompletedRule, TodoAlreadyUncompletedRule};
use Core\Domain\RuleChecker;

/**
 * Todo Aggregate Root.
 * 
 * Rich domain model with business logic and state management.
 */
class Todo
{
    private array $domainEvents = [];

    private function __construct(
        private string $id,
        private TodoTitle $title,
        private string $userId,
        private bool $completed = false,
        private int $createdAt = 0,
        private ?int $completedAt = null
    ) {
        if ($createdAt === 0) {
            $this->createdAt = time();
        }
    }

    /**
     * Create a new Todo.
     */
    public static function create(string $id, TodoTitle $title, string $userId): self
    {
        $todo = new self($id, $title, $userId);
        
        // Raise domain event
        $todo->addDomainEvent(new TodoCreated(
            todoId: $id,
            title: $title->value(),
            userId: $userId,
            createdAt: $todo->createdAt
        ));
        
        return $todo;
    }

    /**
     * Mark todo as completed.
     * 
     * Business Rule: Todo cannot be completed twice.
     * 
     * @throws \Core\Domain\DomainError if rule is violated
     */
    public function complete(): void
    {
        // Check business rule
        $rule = new TodoAlreadyCompletedRule($this->completed, $this->id);
        RuleChecker::enforce($rule);
        
        // Execute domain logic
        $this->completed = true;
        $this->completedAt = time();
        
        // Raise domain event
        $this->addDomainEvent(new TodoCompleted(
            todoId: $this->id,
            completedAt: $this->completedAt
        ));
    }

    /**
     * Mark todo as uncompleted.
     * 
     * Business Rule: Todo cannot be uncompleted if not completed.
     * 
     * @throws \Core\Domain\DomainError if rule is violated
     */
    public function uncomplete(): void
    {
        // Check business rule
        $rule = new TodoAlreadyUncompletedRule($this->completed, $this->id);
        RuleChecker::enforce($rule);
        
        // Execute domain logic
        $this->completed = false;
        $this->completedAt = null;
        
        // Raise domain event
        $this->addDomainEvent(new TodoUncompleted(
            todoId: $this->id
        ));
    }

    /**
     * Update todo title.
     */
    public function updateTitle(TodoTitle $newTitle): void
    {
        if ($this->title->equals($newTitle)) {
            return; // No change
        }
        
        $this->title = $newTitle;
    }

    // Getters
    public function id(): string { return $this->id; }
    public function title(): TodoTitle { return $this->title; }
    public function userId(): string { return $this->userId; }
    public function isCompleted(): bool { return $this->completed; }
    public function createdAt(): int { return $this->createdAt; }
    public function completedAt(): ?int { return $this->completedAt; }

    /**
     * Add domain event.
     */
    private function addDomainEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    /**
     * Get and clear domain events.
     */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    /**
     * Convert to array for persistence.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title->value(),
            'user_id' => $this->userId,
            'completed' => $this->completed,
            'created_at' => $this->createdAt,
            'completed_at' => $this->completedAt,
        ];
    }

    /**
     * Reconstitute from array.
     */
    public static function fromArray(array $data): self
    {
        $title = TodoTitle::create($data['title'])->getValue();
        
        return new self(
            id: $data['id'],
            title: $title,
            userId: $data['user_id'],
            completed: $data['completed'],
            createdAt: $data['created_at'],
            completedAt: $data['completed_at'] ?? null
        );
    }
}
