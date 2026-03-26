<?php

namespace Modules\Todo\Domain\Rules;

use Core\Domain\{DomainRule, DomainError};
use Modules\Todo\Domain\Errors\TodoAlreadyCompletedError;

/**
 * Business Rule: Todo cannot be completed twice.
 * 
 * This rule ensures that a todo can only be marked as completed
 * if it's not already in a completed state.
 */
class TodoAlreadyCompletedRule implements DomainRule
{
    public function __construct(
        private readonly bool $completed,
        private readonly string $todoId
    ) {}

    /**
     * Rule is broken if todo is already completed.
     */
    public function isBrokenIf(): bool
    {
        return $this->completed;
    }

    /**
     * Get the domain error when rule is broken.
     */
    public function getError(): DomainError
    {
        return new TodoAlreadyCompletedError($this->todoId);
    }

    /**
     * Human-readable rule description.
     */
    public function getMessage(): string
    {
        return 'Todo cannot be marked as completed when it is already completed';
    }
}
