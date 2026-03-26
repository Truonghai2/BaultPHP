# Test Builders Pattern - Implementation Complete

## Overview

This adds a Test Builder for the Todo domain to make tests more readable,
reusable, and consistent. Builders help you create complex entities in a
fluent way without repeating boilerplate setup.

## What's Included

- `tests/Builders/TodoBuilder.php` (fluent builder)
- `tests/Unit/Todo/TodoBuilderTest.php` (example usage)

## Why It Matters

- Less boilerplate in tests
- Clear intent in test setup
- Centralized default values
- Easy to extend for new fields

## Usage

```php
use Tests\Builders\TodoBuilder;

$todo = (new TodoBuilder())
    ->withTitle('Buy milk')
    ->withUserId('user-999')
    ->completed()
    ->build();
```

## Available Methods

- `withId(string $id)`
- `withTitle(string $title)`
- `withUserId(string $userId)`
- `completed()`
- `withCreatedAt(int $createdAt)`
- `withCompletedAt(?int $completedAt)`
- `build() : Todo`

## Example Test

```php
public function test_builds_completed_todo(): void
{
    $todo = (new TodoBuilder())
        ->withTitle('Buy milk')
        ->completed()
        ->build();

    $this->assertTrue($todo->isCompleted());
}
```

## Next Steps

- Add builders for `User`, `Page`, and other domain entities.
- Extend builders with domain-specific helpers (e.g., `withHighPriority()`).
