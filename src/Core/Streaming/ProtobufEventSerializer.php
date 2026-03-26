<?php

namespace Core\Streaming;

use Core\Events\Event;
use Google\Protobuf\Internal\Message;
use Proto\Events\EventEnvelope;
use Google\Protobuf\Any;
use Google\Protobuf\Timestamp;

/**
 * Protobuf Event Serializer.
 * 
 * High-performance binary serialization using Protocol Buffers.
 * 
 * Benefits:
 * - 3-10x smaller than JSON
 * - 2-5x faster serialization
 * - Type safety
 * - Schema evolution
 */
class ProtobufEventSerializer implements EventSerializer
{
    /**
     * Map PHP event classes to Protobuf messages.
     */
    private array $eventMapping = [
        'Modules\Todo\Domain\Events\TodoCreated' => 'Proto\Events\TodoCreated',
        'Modules\Todo\Domain\Events\TodoCompleted' => 'Proto\Events\TodoCompleted',
        'Modules\Todo\Domain\Events\TodoUncompleted' => 'Proto\Events\TodoUncompleted',
        'Modules\Todo\Domain\Events\TodoTitleChanged' => 'Proto\Events\TodoTitleChanged',
        'Modules\Todo\Domain\Events\TodoDeleted' => 'Proto\Events\TodoDeleted',
        'Modules\User\Domain\Events\UserRegistered' => 'Proto\Events\UserRegistered',
        'Modules\User\Domain\Events\UserEmailVerified' => 'Proto\Events\UserEmailVerified',
        'Modules\Cms\Domain\Events\PageCreated' => 'Proto\Events\PageCreated',
        'Modules\Cms\Domain\Events\PagePublished' => 'Proto\Events\PagePublished',
    ];

    public function serialize(Event $event): string
    {
        // Create envelope
        $envelope = new EventEnvelope();
        $envelope->setEventId($event->getEventId());
        $envelope->setEventType(get_class($event));
        $envelope->setCorrelationId($event->getCorrelationId());
        
        // Set timestamp
        $timestamp = new Timestamp();
        $timestamp->fromDateTime($event->getOccurredAt());
        $envelope->setOccurredAt($timestamp);

        // Set aggregate info if available
        if (method_exists($event, 'getAggregateId')) {
            $envelope->setAggregateId($event->getAggregateId());
        }
        
        if (method_exists($event, 'getAggregateType')) {
            $envelope->setAggregateType($event->getAggregateType());
        }

        if (method_exists($event, 'getSequence')) {
            $envelope->setSequence($event->getSequence());
        }

        // Convert event to protobuf message
        $protoMessage = $this->toProtobufMessage($event);
        
        // Pack into Any
        $any = new Any();
        $any->pack($protoMessage);
        $envelope->setPayload($any);

        // Serialize to binary
        return $envelope->serializeToString();
    }

    public function deserialize(string $data): Event
    {
        // Deserialize envelope
        $envelope = new EventEnvelope();
        $envelope->mergeFromString($data);

        // Get event type
        $eventClass = $envelope->getEventType();
        
        if (!class_exists($eventClass)) {
            throw new \RuntimeException("Event class not found: {$eventClass}");
        }

        // Unpack payload
        $any = $envelope->getPayload();
        $protoClass = $this->getProtobufClass($eventClass);
        
        $protoMessage = new $protoClass();
        $any->unpack($protoMessage);

        // Convert protobuf message to domain event
        return $this->toDomainEvent($protoMessage, $eventClass, [
            'event_id' => $envelope->getEventId(),
            'correlation_id' => $envelope->getCorrelationId(),
            'occurred_at' => $envelope->getOccurredAt()->toDateTime(),
            'aggregate_id' => $envelope->getAggregateId(),
            'sequence' => $envelope->getSequence(),
        ]);
    }

    public function getContentType(): string
    {
        return 'application/x-protobuf';
    }

    /**
     * Convert domain event to Protobuf message.
     */
    protected function toProtobufMessage(Event $event): Message
    {
        $eventClass = get_class($event);
        $protoClass = $this->getProtobufClass($eventClass);

        if (!class_exists($protoClass)) {
            throw new \RuntimeException("Protobuf class not found for: {$eventClass}");
        }

        $protoMessage = new $protoClass();

        // Map properties
        $reflection = new \ReflectionClass($event);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $name = $property->getName();
            $value = $property->getValue($event);

            // Convert to camel case for protobuf setters
            $setter = 'set' . $this->snakeToCamel($name);

            if (method_exists($protoMessage, $setter)) {
                // Handle DateTime conversion
                if ($value instanceof \DateTimeInterface) {
                    $timestamp = new Timestamp();
                    $timestamp->fromDateTime($value);
                    $value = $timestamp;
                }

                $protoMessage->$setter($value);
            }
        }

        return $protoMessage;
    }

    /**
     * Convert Protobuf message to domain event.
     */
    protected function toDomainEvent(Message $protoMessage, string $eventClass, array $metadata): Event
    {
        $reflection = new \ReflectionClass($eventClass);
        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            throw new \RuntimeException("Event class must have a constructor: {$eventClass}");
        }

        // Get constructor parameters
        $params = [];
        foreach ($constructor->getParameters() as $param) {
            $paramName = $param->getName();
            
            // Try to get value from protobuf message
            $getter = 'get' . $this->snakeToCamel($paramName);
            
            if (method_exists($protoMessage, $getter)) {
                $value = $protoMessage->$getter();
                
                // Convert Timestamp to DateTime
                if ($value instanceof Timestamp) {
                    $value = $value->toDateTime();
                }
                
                $params[] = $value;
            } else {
                // Use default or null
                $params[] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
            }
        }

        return $reflection->newInstanceArgs($params);
    }

    /**
     * Get Protobuf class for event.
     */
    protected function getProtobufClass(string $eventClass): string
    {
        return $this->eventMapping[$eventClass] ?? throw new \RuntimeException("No protobuf mapping for: {$eventClass}");
    }

    /**
     * Convert snake_case to CamelCase.
     */
    protected function snakeToCamel(string $str): string
    {
        return str_replace('_', '', ucwords($str, '_'));
    }

    /**
     * Register event mapping.
     */
    public function registerMapping(string $eventClass, string $protoClass): void
    {
        $this->eventMapping[$eventClass] = $protoClass;
    }

    /**
     * Get all mappings.
     */
    public function getMappings(): array
    {
        return $this->eventMapping;
    }
}
