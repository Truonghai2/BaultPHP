<?php

namespace Core\Streaming;

use Core\Events\Event;

/**
 * JSON Event Serializer.
 * 
 * Simple JSON-based serialization (fallback).
 */
class JsonEventSerializer implements EventSerializer
{
    public function serialize(Event $event): string
    {
        $data = [
            'event_type' => get_class($event),
            'event_id' => $event->getEventId(),
            'aggregate_id' => method_exists($event, 'getAggregateId') ? $event->getAggregateId() : null,
            'occurred_at' => $event->getOccurredAt()->format('Y-m-d H:i:s.u'),
            'correlation_id' => $event->getCorrelationId(),
            'payload' => $this->serializePayload($event),
        ];

        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    public function deserialize(string $data): Event
    {
        $array = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

        $eventClass = $array['event_type'];
        
        if (!class_exists($eventClass)) {
            throw new \RuntimeException("Event class not found: {$eventClass}");
        }

        // Reconstruct event
        return $this->reconstructEvent($eventClass, $array);
    }

    public function getContentType(): string
    {
        return 'application/json';
    }

    /**
     * Serialize event payload to array.
     */
    protected function serializePayload(Event $event): array
    {
        $reflection = new \ReflectionClass($event);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

        $payload = [];
        foreach ($properties as $property) {
            $payload[$property->getName()] = $property->getValue($event);
        }

        return $payload;
    }

    /**
     * Reconstruct event from array.
     */
    protected function reconstructEvent(string $eventClass, array $data): Event
    {
        $reflection = new \ReflectionClass($eventClass);
        
        // Use constructor if available
        if ($reflection->getConstructor()) {
            $params = [];
            foreach ($reflection->getConstructor()->getParameters() as $param) {
                $paramName = $param->getName();
                $params[] = $data['payload'][$paramName] ?? null;
            }
            
            return $reflection->newInstanceArgs($params);
        }

        // Otherwise, create empty and set properties
        $event = $reflection->newInstanceWithoutConstructor();
        
        foreach ($data['payload'] as $key => $value) {
            if ($reflection->hasProperty($key)) {
                $property = $reflection->getProperty($key);
                $property->setAccessible(true);
                $property->setValue($event, $value);
            }
        }

        return $event;
    }
}
