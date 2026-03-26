<?php

namespace Core\EventSourcing\Projections;

use Core\EventSourcing\EventStore\EventStore;
use Psr\Log\LoggerInterface;

/**
 * Projection Runner.
 * 
 * Replays events to build/rebuild read models.
 */
class ProjectionRunner
{
    public function __construct(
        private EventStore $eventStore,
        private LoggerInterface $logger
    ) {}

    /**
     * Run a projection from the beginning.
     */
    public function run(Projection $projection, int $fromPosition = 0): void
    {
        $this->logger->info('Running projection: ' . get_class($projection));

        $subscribedEvents = $projection->subscribedEvents();
        $position = $fromPosition;
        $batchSize = 100;

        while (true) {
            $events = $this->eventStore->getAllEvents($position, $batchSize);

            if (empty($events)) {
                break; // No more events
            }

            foreach ($events as $event) {
                // Filter by subscribed events
                if (in_array($event->eventName(), $subscribedEvents)) {
                    $projection->handle($event);
                }

                $position = max($position, $event->eventId());
            }

            $this->logger->info("Processed {$position} events");
        }

        $this->logger->info('Projection complete');
    }

    /**
     * Rebuild a projection from scratch.
     */
    public function rebuild(Projection $projection): void
    {
        $this->logger->info('Rebuilding projection: ' . get_class($projection));

        // Reset projection
        $projection->reset();

        // Replay all events
        $this->run($projection, 0);
    }
}
