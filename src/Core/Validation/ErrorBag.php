<?php

namespace Core\Validation;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;

/**
 * A simple container for validation error messages.
 * It provides a convenient API for checking and retrieving errors in views.
 */
class ErrorBag implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * The array of error messages, keyed by field.
     * @var array<string, array<int, string>>
     */
    protected array $messages = [];

    public function __construct(array $messages = [])
    {
        $this->messages = $messages;
    }

    /**
     * Check if there are any errors for a given field.
     */
    public function has(string $key): bool
    {
        return isset($this->messages[$key]);
    }

    /**
     * Get the first error message for a given field.
     */
    public function first(string $key): ?string
    {
        return $this->messages[$key][0] ?? null;
    }

    /**
     * Get all error messages for a given field.
     */
    public function get(string $key): array
    {
        return $this->messages[$key] ?? [];
    }

    /**
     * Get all messages.
     */
    public function all(): array
    {
        return $this->messages;
    }

    public function any(): bool
    {
        return $this->count() > 0;
    }

    public function count(): int
    {
        return count($this->messages);
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->messages);
    }

    public function offsetExists(mixed $key): bool
    {
        return $this->has($key);
    }
    public function offsetGet(mixed $key): array
    {
        return $this->get($key);
    }
    public function offsetSet(mixed $key, mixed $value): void
    {
        $this->messages[$key] = (array) $value;
    }
    public function offsetUnset(mixed $key): void
    {
        unset($this->messages[$key]);
    }
    public function jsonSerialize(): array
    {
        return $this->all();
    }
}
