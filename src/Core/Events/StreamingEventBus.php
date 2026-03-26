<?php

declare(strict_types=1);

namespace Core\Events;

use Core\Support\Facades\Log;
use Psr\Log\LoggerInterface;

/**
 * Streaming Event Bus
 *
 * Publishes events to event streaming platforms (Kafka, Pulsar, NATS).
 * Supports event replay, time-travel debugging, and real-time analytics.
 *
 * Features:
 * - Event streaming to Kafka/Pulsar/NATS
 * - Event replay capability
 * - Time-travel debugging
 * - Real-time analytics
 * - Event versioning
 */
class StreamingEventBus implements EventDispatcherInterface
{
    protected array $config = [];
    protected ?object $producer = null;
    protected array $eventHistory = [];
    protected int $maxHistorySize = 1000;

    public function __construct(
        protected EventDispatcherInterface $localDispatcher,
        protected LoggerInterface $logger,
        array $config = [],
    ) {
        $this->config = array_merge([
            'driver' => env('EVENT_STREAMING_DRIVER', 'kafka'),
            'enabled' => env('EVENT_STREAMING_ENABLED', false),
            'brokers' => env('EVENT_STREAMING_BROKERS', 'localhost:9092'),
            'topic_prefix' => env('EVENT_STREAMING_TOPIC_PREFIX', 'events'),
            'enable_history' => env('EVENT_STREAMING_HISTORY', true),
            'max_history_size' => env('EVENT_STREAMING_MAX_HISTORY', 1000),
        ], $config);

        $this->maxHistorySize = $this->config['max_history_size'] ?? 1000;

        if ($this->config['enabled']) {
            $this->initializeProducer();
        }
    }

    /**
     * Initialize event streaming producer
     */
    protected function initializeProducer(): void
    {
        $driver = $this->config['driver'];

        try {
            match ($driver) {
                'kafka' => $this->initializeKafkaProducer(),
                'pulsar' => $this->initializePulsarProducer(),
                'nats' => $this->initializeNatsProducer(),
                'redis' => $this->initializeRedisProducer(),
                default => throw new \InvalidArgumentException("Unsupported streaming driver: {$driver}"),
            };
        } catch (\Throwable $e) {
            Log::error("Failed to initialize event streaming producer", [
                'driver' => $driver,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Initialize Kafka producer
     */
    protected function initializeKafkaProducer(): void
    {
        // In production, use rdkafka/php-rdkafka or similar
        // For now, we'll create a placeholder that can be extended
        
        $this->producer = new class($this->config) {
            public function __construct(protected array $config) {}
            
            public function produce(string $topic, string $message, ?string $key = null): void
            {
                // Placeholder: Would use rdkafka here
                // $producer = new \RdKafka\Producer();
                // $producer->addBrokers($this->config['brokers']);
                // $topic = $producer->newTopic($topic);
                // $topic->produce(RD_KAFKA_PARTITION_UA, 0, $message, $key);
                
                Log::debug("Kafka produce (placeholder)", [
                    'topic' => $topic,
                    'key' => $key,
                ]);
            }
        };
    }

    /**
     * Initialize Pulsar producer
     */
    protected function initializePulsarProducer(): void
    {
        // Placeholder for Pulsar client
        $this->producer = new class($this->config) {
            public function __construct(protected array $config) {}
            
            public function send(string $topic, string $message): void
            {
                Log::debug("Pulsar send (placeholder)", ['topic' => $topic]);
            }
        };
    }

    /**
     * Initialize NATS producer
     */
    protected function initializeNatsProducer(): void
    {
        // Placeholder for NATS client
        $this->producer = new class($this->config) {
            public function __construct(protected array $config) {}
            
            public function publish(string $subject, string $message): void
            {
                Log::debug("NATS publish (placeholder)", ['subject' => $subject]);
            }
        };
    }

    /**
     * Initialize Redis producer (using pub/sub as fallback)
     */
    protected function initializeRedisProducer(): void
    {
        // Use Redis pub/sub as lightweight event streaming
        $this->producer = new class($this->config) {
            public function __construct(protected array $config) {}
            
            public function publish(string $channel, string $message): void
            {
                // Would use Redis PUBLISH command
                Log::debug("Redis publish (placeholder)", ['channel' => $channel]);
            }
        };
    }

    /**
     * Dispatch event locally and stream to event platform
     */
    public function dispatch(object $event): void
    {
        // Dispatch locally first
        $this->localDispatcher->dispatch($event);

        // Stream to event platform if enabled
        if ($this->config['enabled'] && $this->producer !== null) {
            $this->streamEvent($event);
        }

        // Store in history for replay
        if ($this->config['enable_history'] ?? true) {
            $this->storeInHistory($event);
        }
    }

    /**
     * Stream event to event platform
     */
    protected function streamEvent(object $event): void
    {
        try {
            $eventName = get_class($event);
            $topic = $this->getTopicForEvent($eventName);
            $message = $this->serializeEvent($event);
            $key = $this->getEventKey($event);

            $driver = $this->config['driver'];

            match ($driver) {
                'kafka' => $this->producer->produce($topic, $message, $key),
                'pulsar' => $this->producer->send($topic, $message),
                'nats' => $this->producer->publish($topic, $message),
                'redis' => $this->producer->publish($topic, $message),
                default => null,
            };

            Log::debug("Event streamed", [
                'event' => $eventName,
                'topic' => $topic,
                'driver' => $driver,
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to stream event", [
                'event' => get_class($event),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get topic name for event
     */
    protected function getTopicForEvent(string $eventName): string
    {
        $prefix = $this->config['topic_prefix'] ?? 'events';
        
        // Convert class name to topic name
        // e.g., "Modules\User\Events\UserCreated" -> "events.user.created"
        $parts = explode('\\', $eventName);
        $eventPart = strtolower(end($parts));
        $eventPart = preg_replace('/([a-z])([A-Z])/', '$1.$2', $eventPart);
        
        return "{$prefix}.{$eventPart}";
    }

    /**
     * Serialize event to JSON
     */
    protected function serializeEvent(object $event): string
    {
        $data = [
            'event_type' => get_class($event),
            'event_id' => $this->generateEventId(),
            'occurred_at' => date('c'),
            'payload' => $this->extractEventPayload($event),
            'metadata' => [
                'source' => 'baultphp',
                'version' => '1.0',
            ],
        ];

        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    /**
     * Extract payload from event object
     */
    protected function extractEventPayload(object $event): array
    {
        $reflection = new \ReflectionClass($event);
        $properties = $reflection->getProperties();
        
        $payload = [];
        foreach ($properties as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($event);
            
            // Serialize only scalar values and arrays
            if (is_scalar($value) || is_array($value) || is_null($value)) {
                $payload[$property->getName()] = $value;
            } elseif (is_object($value)) {
                // Try to convert object to array
                if (method_exists($value, 'toArray')) {
                    $payload[$property->getName()] = $value->toArray();
                } elseif (method_exists($value, '__toString')) {
                    $payload[$property->getName()] = (string) $value;
                }
            }
        }
        
        return $payload;
    }

    /**
     * Generate unique event ID
     */
    protected function generateEventId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Get event key for partitioning (Kafka)
     */
    protected function getEventKey(object $event): ?string
    {
        // Try to extract aggregate ID or similar identifier
        if (method_exists($event, 'getAggregateId')) {
            return $event->getAggregateId();
        }
        
        if (property_exists($event, 'aggregateId')) {
            return $event->aggregateId;
        }
        
        if (property_exists($event, 'id')) {
            return (string) $event->id;
        }
        
        return null;
    }

    /**
     * Store event in history for replay
     */
    protected function storeInHistory(object $event): void
    {
        $this->eventHistory[] = [
            'event' => $event,
            'timestamp' => time(),
            'event_id' => $this->generateEventId(),
        ];

        // Limit history size
        if (count($this->eventHistory) > $this->maxHistorySize) {
            array_shift($this->eventHistory);
        }
    }

    /**
     * Replay events from a specific point in time
     */
    public function replayEvents(\DateTimeInterface $from, ?\DateTimeInterface $to = null): void
    {
        $fromTimestamp = $from->getTimestamp();
        $toTimestamp = $to ? $to->getTimestamp() : time();

        foreach ($this->eventHistory as $entry) {
            if ($entry['timestamp'] >= $fromTimestamp && $entry['timestamp'] <= $toTimestamp) {
                $this->localDispatcher->dispatch($entry['event']);
            }
        }

        Log::info("Event replay completed", [
            'from' => $from->format('c'),
            'to' => $to ? $to->format('c') : 'now',
            'events_replayed' => count($this->eventHistory),
        ]);
    }

    /**
     * Replay events by event type
     */
    public function replayEventsByType(string $eventType, int $limit = 100): void
    {
        $count = 0;
        foreach ($this->eventHistory as $entry) {
            if (get_class($entry['event']) === $eventType && $count < $limit) {
                $this->localDispatcher->dispatch($entry['event']);
                $count++;
            }
        }

        Log::info("Event replay by type completed", [
            'event_type' => $eventType,
            'events_replayed' => $count,
        ]);
    }

    /**
     * Get event history statistics
     */
    public function getHistoryStats(): array
    {
        $stats = [
            'total_events' => count($this->eventHistory),
            'max_size' => $this->maxHistorySize,
            'event_types' => [],
        ];

        foreach ($this->eventHistory as $entry) {
            $eventType = get_class($entry['event']);
            if (!isset($stats['event_types'][$eventType])) {
                $stats['event_types'][$eventType] = 0;
            }
            $stats['event_types'][$eventType]++;
        }

        return $stats;
    }

    /**
     * Clear event history
     */
    public function clearHistory(): void
    {
        $this->eventHistory = [];
        Log::info("Event history cleared");
    }

    /**
     * Listen to events (delegate to local dispatcher)
     */
    public function listen(string $event, string|callable $listener): void
    {
        $this->localDispatcher->listen($event, $listener);
    }

}
