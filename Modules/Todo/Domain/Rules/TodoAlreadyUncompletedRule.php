<?php

namespace Modules\Todo\Domain\Rules;

use Core\Domain\{DomainRule, DomainError};
use Modules\Todo\Domain\Errors\TodoAlreadyUncompletedError;

/**
 * Business Rule: Todo cannot be uncompleted twice.
 * 
 * This rule ensures that a todo can only be marked as uncompleted
 * if it's currently in a completed state.
 */
class TodoAlreadyUncompletedRule implements DomainRule
{
    public function __construct(
        private readonly bool $completed,
        private readonly string $todoId
    ) {}

    /**
     * Rule is broken if todo is NOT completed.
     */
    public function isBrokenIf(): bool
    {
        return !$this->completed;
    }

    /**
     * Get the domain error when rule is broken.
     */
    public function getError(): DomainError
    {
        return new TodoAlreadyUncompletedError($this->todoId);
    }

    /**
     * Human-readable rule description.
     */
    public function getMessage(): string
    {
        return 'Todo cannot be marked as uncompleted when it is not completed';
    }
}
