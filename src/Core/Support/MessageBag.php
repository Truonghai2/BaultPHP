<?php

namespace Core\Support;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;

/**
 * A simple class to hold and manage validation error messages.
 * Mimics the behavior of Laravel's MessageBag.
 */
class MessageBag implements Countable, IteratorAggregate, JsonSerializable
{
    /**
     * The array of messages.
     *
     * @var array
     */
    protected array $messages = [];

    /**
     * Create a new message bag.
     *
     * @param  array  $messages
     */
    public function __construct(array $messages = [])
    {
        foreach ($messages as $key => $value) {
            $this->messages[$key] = (array) $value;
        }
    }

    /**
     * Get the first message from the bag for a given key.
     *
     * @param  string  $key
     * @param  string|null  $default
     * @return string|null
     */
    public function first(string $key, ?string $default = null): ?string
    {
        $messages = $this->get($key);
        return $messages[0] ?? $default;
    }

    /**
     * Get all of the messages from the bag for a given key.
     *
     * @param  string  $key
     * @return array
     */
    public function get(string $key): array
    {
        return $this->messages[$key] ?? [];
    }

    /**
     * Determine if messages exist for a given key.
     *
     * @param  string  $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->messages[$key]);
    }

    /**
     * Get all of the messages for the bag.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->messages;
    }

    /**
     * Get the number of messages in the container.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->messages);
    }

    /**
     * Determine if the message bag has any messages.
     *
     * @return bool
     */
    public function any(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Get an iterator for the messages.
     *
     * @return \ArrayIterator
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->messages);
    }

    /**
     * Get the raw messages array.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->all();
    }
}
