<?php

namespace Tests\Builders;

use Modules\Todo\Domain\Entities\Todo;
use Modules\Todo\Domain\ValueObjects\TodoTitle;
use RuntimeException;

/**
 * Test Builder for Todo entity.
 *
 * Provides a fluent API to create Todo instances for tests.
 */
class TodoBuilder
{
    private string $id;
    private string $title = 'Default Title';
    private string $userId = 'user-123';
    private bool $completed = false;
    private int $createdAt;
    private ?int $completedAt = null;

    public function __construct()
    {
        $this->id = $this->generateId();
        $this->createdAt = time();
    }

    public function withId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function withTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function withUserId(string $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function completed(): self
    {
        $this->completed = true;
        $this->completedAt = $this->completedAt ?? time();
        return $this;
    }

    public function withCreatedAt(int $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function withCompletedAt(?int $completedAt): self
    {
        $this->completedAt = $completedAt;
        $this->completed = $completedAt !== null;
        return $this;
    }

    public function build(): Todo
    {
        $titleResult = TodoTitle::create($this->title);
        if ($titleResult->isFailure()) {
            $error = $titleResult->getError();
            $message = is_object($error) ? $error->getMessage() : (string) $error;
            throw new RuntimeException('Invalid todo title: ' . $message);
        }

        return Todo::fromArray([
            'id' => $this->id,
            'title' => $titleResult->getValue()->value(),
            'user_id' => $this->userId,
            'completed' => $this->completed,
            'created_at' => $this->createdAt,
            'completed_at' => $this->completedAt,
        ]);
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
