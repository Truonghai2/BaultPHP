<?php

namespace Core\EventSourcing;

use Core\EventSourcing\EventStore\EventStore;

/**
 * Aggregate Repository
 * 
 * Generic repository for loading and saving aggregates.
 * Handles event persistence and aggregate reconstitution.
 */
class AggregateRepository
{
    public function __construct(
        private EventStore $eventStore
    ) {}

    /**
     * Load an aggregate from event store
     * 
     * @template T of AggregateRoot
     * @param class-string<T> $aggregateClass
     * @return T|null
     */
    public function load(string $aggregateClass, string $aggregateId): ?AggregateRoot
    {
        $aggregateType = $this->getAggregateType($aggregateClass);
        
        // Load events from store
        $events = $this->eventStore->getEvents($aggregateType, $aggregateId);
        
        if (empty($events)) {
            return null;
        }

        // Reconstitute aggregate from events
        return $aggregateClass::reconstituteFromHistory($aggregateId, $events);
    }

    /**
     * Save aggregate and its events
     * 
     * @param AggregateRoot $aggregate
     */
    public function save(AggregateRoot $aggregate): void
    {
        $events = $aggregate->getRecordedEvents();
        
        if (empty($events)) {
            return; // Nothing to save
        }

        $aggregateType = $this->getAggregateType(get_class($aggregate));
        
        // Save to event store
        $this->eventStore->saveEvents(
            $aggregateType,
            $aggregate->getId(),
            $events,
            $aggregate->getVersion()
        );

        // Update aggregate version
        foreach ($events as $event) {
            $aggregate->incrementVersion();
        }

        // Clear recorded events
        $aggregate->clearRecordedEvents();
    }

    /**
     * Get aggregate type name from class
     */
    private function getAggregateType(string $class): string
    {
        return $class;
    }
}

