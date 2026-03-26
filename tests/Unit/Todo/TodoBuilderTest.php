<?php

namespace Tests\Unit\Todo;

use Tests\TestCase;
use Tests\Builders\TodoBuilder;

class TodoBuilderTest extends TestCase
{
    public function test_builds_default_todo(): void
    {
        $todo = (new TodoBuilder())->build();

        $this->assertNotEmpty($todo->id());
        $this->assertSame('Default Title', $todo->title()->value());
        $this->assertSame('user-123', $todo->userId());
        $this->assertFalse($todo->isCompleted());
        $this->assertNotNull($todo->createdAt());
        $this->assertNull($todo->completedAt());
    }

    public function test_builds_completed_todo(): void
    {
        $todo = (new TodoBuilder())
            ->withTitle('Buy milk')
            ->withUserId('user-999')
            ->completed()
            ->build();

        $this->assertSame('Buy milk', $todo->title()->value());
        $this->assertSame('user-999', $todo->userId());
        $this->assertTrue($todo->isCompleted());
        $this->assertNotNull($todo->completedAt());
    }
}
