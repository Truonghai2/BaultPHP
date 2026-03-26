<?php

namespace Core\Streaming;

use Core\Events\Event;

/**
 * Event Serializer Interface.
 * 
 * Abstracts event serialization format (JSON, Protobuf, etc).
 */
interface EventSerializer
{
    /**
     * Serialize event to string.
     */
    public function serialize(Event $event): string;

    /**
     * Deserialize string to event.
     */
    public function deserialize(string $data): Event;

    /**
     * Get content type.
     */
    public function getContentType(): string;
}
