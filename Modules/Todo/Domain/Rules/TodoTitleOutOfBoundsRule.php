<?php

namespace Modules\Todo\Domain\Rules;

use Core\Domain\{DomainRule, DomainError};
use Modules\Todo\Domain\Errors\TodoTitleOutOfBoundsError;

/**
 * Business Rule: Todo title must be within valid length bounds.
 * 
 * Ensures that todo titles are not too short (meaningless)
 * or too long (database/UI constraints).
 */
class TodoTitleOutOfBoundsRule implements DomainRule
{
    private const MIN_LENGTH = 3;
    private const MAX_LENGTH = 200;

    public function __construct(
        private readonly string $title
    ) {}

    /**
     * Rule is broken if title length is out of bounds.
     */
    public function isBrokenIf(): bool
    {
        $length = mb_strlen($this->title);
        
        return $length < self::MIN_LENGTH || $length > self::MAX_LENGTH;
    }

    /**
     * Get the domain error when rule is broken.
     */
    public function getError(): DomainError
    {
        return new TodoTitleOutOfBoundsError(
            length: mb_strlen($this->title),
            min: self::MIN_LENGTH,
            max: self::MAX_LENGTH
        );
    }

    /**
     * Human-readable rule description.
     */
    public function getMessage(): string
    {
        return sprintf(
            'Todo title must be between %d and %d characters',
            self::MIN_LENGTH,
            self::MAX_LENGTH
        );
    }
}
