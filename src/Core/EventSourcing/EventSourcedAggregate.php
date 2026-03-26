<?php

namespace Core\EventSourcing;

/**
 * Base class for Event Sourced Aggregates.
 * 
 * Aggregates reconstitute their state from events.
 */
abstract class EventSourcedAggregate
{
    protected string $aggregateId;
    protected int $version = 0;
    protected array $uncommittedEvents = [];

    /**
     * Reconstitute aggregate from event history.
     *
     * @param array<Event> $events
     * @return static
     */
    public static function reconstitute(array $events): static
    {
        $instance = new static();

        foreach ($events as $event) {
            $instance->apply($event);
            $instance->version = $event->aggregateVersion();
        }

        return $instance;
    }

    /**
     * Apply an event to change state.
     * 
     * Each event type has a corresponding apply method:
     * - TodoCreated → applyTodoCreated()
     * - TodoCompleted → applyTodoCompleted()
     */
    abstract protected function apply(Event $event): void;

    /**
     * Raise a new domain event.
     */
    protected function raise(Event $event): void
    {
        // Apply to self first
        $this->apply($event);

        // Track for persistence
        $this->uncommittedEvents[] = $event;
    }

    /**
     * Get uncommitted events and clear them.
     */
    public function releaseEvents(): array
    {
        $events = $this->uncommittedEvents;
        $this->uncommittedEvents = [];
        return $events;
    }

    public function aggregateId(): string
    {
        return $this->aggregateId;
    }

    public function version(): int
    {
        return $this->version;
    }
}
