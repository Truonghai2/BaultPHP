<?php

namespace Core\Streaming;

use Core\Events\Event;
use Core\Events\EventBus;
use Psr\Log\LoggerInterface;

/**
 * NATS JetStream Event Bus.
 * 
 * Publishes domain events to NATS JetStream for:
 * - Cross-service communication
 * - Event replay
 * - Scalable event processing
 * - Durable subscriptions
 */
class NatsEventBus implements EventBus
{
    private array $listeners = [];

    public function __construct(
        private NatsConnection $connection,
        private EventSerializer $serializer,
        private LoggerInterface $logger,
        private string $streamName = 'EVENTS',
    ) {
    }

    /**
     * Publish event to NATS stream.
     */
    public function publish(Event $event): void
    {
        try {
            $subject = $this->getSubjectForEvent($event);
            $data = $this->serializer->serialize($event);

            $this->connection->publish($subject, $data, [
                'Event-Type' => get_class($event),
                'Event-Id' => $event->getEventId(),
                'Correlation-Id' => $event->getCorrelationId(),
            ]);

            $this->logger->debug("Published event to NATS", [
                'event' => get_class($event),
                'subject' => $subject,
                'size' => strlen($data),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error("Failed to publish event to NATS", [
                'event' => get_class($event),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Subscribe to events.
     */
    public function subscribe(string $eventClass, callable $listener): void
    {
        if (!isset($this->listeners[$eventClass])) {
            $this->listeners[$eventClass] = [];
        }

        $this->listeners[$eventClass][] = $listener;

        // Subscribe to NATS subject
        $subject = $this->getSubjectForEventClass($eventClass);
        
        $this->connection->subscribe($subject, function ($message) use ($listener) {
            try {
                $event = $this->serializer->deserialize($message->payload);
                $listener($event);
            } catch (\Throwable $e) {
                $this->logger->error("Event listener failed", [
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * Dispatch event to local listeners.
     */
    public function dispatch(Event $event): void
    {
        $eventClass = get_class($event);

        if (isset($this->listeners[$eventClass])) {
            foreach ($this->listeners[$eventClass] as $listener) {
                try {
                    $listener($event);
                } catch (\Throwable $e) {
                    $this->logger->error("Event listener failed", [
                        'event' => $eventClass,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Also publish to NATS for distributed handling
        $this->publish($event);
    }

    /**
     * Get NATS subject for event.
     */
    protected function getSubjectForEvent(Event $event): string
    {
        return $this->getSubjectForEventClass(get_class($event));
    }

    /**
     * Get NATS subject for event class.
     */
    protected function getSubjectForEventClass(string $eventClass): string
    {
        // Convert: Modules\Todo\Domain\Events\TodoCreated
        // To: events.todo.created
        $parts = explode('\\', $eventClass);
        $eventName = end($parts);
        
        // Get module name
        $module = isset($parts[1]) ? strtolower($parts[1]) : 'app';
        
        // Convert TodoCreated to todo.created
        $subject = strtolower(preg_replace('/([a-z])([A-Z])/', '$1.$2', $eventName));
        
        return "events.{$module}.{$subject}";
    }

    /**
     * Get registered listeners.
     */
    public function getListeners(): array
    {
        return $this->listeners;
    }

    /**
     * Get stream name.
     */
    public function getStreamName(): string
    {
        return $this->streamName;
    }
}
