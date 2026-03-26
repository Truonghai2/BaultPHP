<?php

namespace Modules\Todo\Domain\ValueObjects;

use Core\Support\Result;

/**
 * Todo Title Value Object.
 * 
 * Encapsulates title validation and business rules.
 */
class TodoTitle
{
    private function __construct(
        private readonly string $value
    ) {}

    /**
     * Create a valid TodoTitle.
     * 
     * @param string $value
     * @return Result<TodoTitle>
     */
    public static function create(string $value): Result
    {
        // Validate
        $trimmed = trim($value);
        
        if (empty($trimmed)) {
            return Result::fail('Todo title cannot be empty');
        }
        
        if (strlen($trimmed) < 3) {
            return Result::fail('Todo title must be at least 3 characters');
        }
        
        if (strlen($trimmed) > 200) {
            return Result::fail('Todo title cannot exceed 200 characters');
        }
        
        return Result::ok(new self($trimmed));
    }

    /**
     * Get the title value.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Check equality with another title.
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
