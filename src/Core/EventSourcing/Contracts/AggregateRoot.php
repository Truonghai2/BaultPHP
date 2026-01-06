<?php

declare(strict_types=1);

namespace Core\EventSourcing\Contracts;

interface AggregateRoot
{
    /**
     * Get the aggregate's unique identifier.
     *
     * @return string
     */
    public function getAggregateRootId(): string;

    /**
     * Get the recorded events that have not been committed yet.
     *
     * @return object[]
     */
    public function getRecordedEvents(): array;

    /**
     * Clear the recorded events.
     */
    public function clearRecordedEvents(): void;

    /**
     * Reconstitute the aggregate from a history of events.
     *
     * @param  \Traversable<object>  $history
     * @return static
     */
    public static function reconstituteFromHistory(\Traversable $history): self;

    /**
     * Apply an event to the aggregate.
     */
    public function apply(object $event): void;

    /**
     * Get current version
     */
    public function getVersion(): int;

    /**
     * Increment version (after event persistence)
     */
    public function incrementVersion(): void;
}
