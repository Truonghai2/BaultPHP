<?php

namespace Core\Events;

use Core\Application;
use Psr\Log\LoggerInterface;

/**
 * Event Stream Processor.
 * 
 * Processes events from event store and dispatches to projections.
 * Enables real-time read model updates.
 * 
 * Features:
 * - Stream from event store
 * - Position tracking
 * - Automatic retry
 * - Error handling
 */
class EventStreamProcessor
{
    private array $projections = [];
    private array $positions = [];

    public function __construct(
        private EventStore $eventStore,
        private LoggerInterface $logger,
        private Application $app,
    ) {
    }

    /**
     * Register projection.
     */
    public function registerProjection(string $projectionClass): void
    {
        if (!in_array($projectionClass, $this->projections)) {
            $this->projections[] = $projectionClass;
        }
    }

    /**
     * Process events from position.
     */
    public function processFrom(int $position = 0, ?int $batchSize = 100): void
    {
        $this->logger->info("Processing events from position {$position}");

        // Load events in batches
        $events = $this->eventStore->loadEventsFrom($position, $batchSize);

        if (empty($events)) {
            $this->logger->debug("No new events to process");
            return;
        }

        $processed = 0;
        $lastPosition = $position;

        foreach ($events as $event) {
            try {
                $this->processEvent($event);
                $lastPosition = $event->getSequence();
                $processed++;

                // Store checkpoint
                $this->storeCheckpoint($lastPosition);
            } catch (\Throwable $e) {
                $this->logger->error("Failed to process event", [
                    'event' => get_class($event),
                    'sequence' => $event->getSequence(),
                    'error' => $e->getMessage(),
                ]);

                // Depending on strategy: skip or retry
                if (config('projections.stop_on_error', false)) {
                    throw $e;
                }
            }
        }

        $this->logger->info("Processed {$processed} events", [
            'last_position' => $lastPosition,
        ]);

        // Continue if more events available
        if (count($events) === $batchSize) {
            $this->processFrom($lastPosition + 1, $batchSize);
        }
    }

    /**
     * Process single event.
     */
    protected function processEvent(Event $event): void
    {
        foreach ($this->projections as $projectionClass) {
            $projection = $this->app->make($projectionClass);

            // Check if projection handles this event
            if (method_exists($projection, 'handles')) {
                $handledEvents = $projection->handles();
                if (!in_array(get_class($event), $handledEvents)) {
                    continue;
                }
            }

            // Apply event to projection
            $projection->project($event);
        }
    }

    /**
     * Store checkpoint for resume.
     */
    protected function storeCheckpoint(int $position): void
    {
        cache()->set('projection:last_position', $position);
    }

    /**
     * Get last checkpoint.
     */
    public function getCheckpoint(): int
    {
        return (int) cache()->get('projection:last_position', 0);
    }

    /**
     * Run continuously (daemon mode).
     */
    public function run(int $pollInterval = 1000): void
    {
        $this->logger->info("Starting event stream processor");

        $position = $this->getCheckpoint();

        while (true) {
            try {
                $this->processFrom($position);
                
                // Update position for next iteration
                $position = $this->getCheckpoint() + 1;

                // Wait before next poll
                usleep($pollInterval * 1000);
            } catch (\Throwable $e) {
                $this->logger->error("Stream processor error", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Wait before retry
                sleep(5);
            }
        }
    }

    /**
     * Get registered projections.
     */
    public function getProjections(): array
    {
        return $this->projections;
    }
}
