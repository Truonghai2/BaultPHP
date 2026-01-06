<?php

namespace Core\EventSourcing;

use Core\EventSourcing\Events\DomainEvent;

/**
 * Aggregate Root Base Class
 *
 * Core building block for Event Sourcing.
 * All domain entities that need event sourcing should extend this.
 *
 * Usage:
 * ```php
 * class User extends AggregateRoot
 * {
 *     private string $email;
 *     private string $status;
 *
 *     public function register(string $email): void
 *     {
 *         $this->recordThat(new UserRegistered($this->id, $email));
 *     }
 *
 *     protected function applyUserRegistered(UserRegistered $event): void
 *     {
 *         $this->email = $event->email;
 *         $this->status = 'active';
 *     }
 * }
 * ```
 */
abstract class AggregateRoot
{
    /**
     * Aggregate ID
     */
    protected string $id;

    /**
     * Events that have been recorded but not yet persisted
     */
    private array $recordedEvents = [];

    /**
     * Current version of the aggregate (for optimistic locking)
     */
    private int $version = 0;

    /**
     * Record a new domain event
     *
     * The event will be applied immediately to update state,
     * and stored for later persistence.
     */
    protected function recordThat(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
        $this->apply($event);
    }

    /**
     * Apply an event to update aggregate state
     *
     * This should be overridden in child classes to handle
     * specific event types using pattern matching.
     */
    protected function apply(DomainEvent $event): void
    {
        $method = 'apply' . class_basename($event);

        if (method_exists($this, $method)) {
            $this->$method($event);
        }
    }

    /**
     * Get all recorded events
     */
    public function getRecordedEvents(): array
    {
        return $this->recordedEvents;
    }

    /**
     * Clear recorded events (after persistence)
     */
    public function clearRecordedEvents(): void
    {
        $this->recordedEvents = [];
    }

    /**
     * Reconstitute aggregate from event history
     *
     * Used when loading an aggregate from the event store.
     */
    public static function reconstituteFromHistory(string $id, array $events): static
    {
        $instance = new static();
        $instance->id = $id;

        foreach ($events as $event) {
            $instance->apply($event);
            $instance->version++;
        }

        return $instance;
    }

    /**
     * Get aggregate ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get current version
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * Increment version (after event persistence)
     */
    public function incrementVersion(): void
    {
        $this->version++;
    }
}
