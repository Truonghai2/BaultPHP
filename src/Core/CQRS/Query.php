<?php

namespace Core\CQRS;

use Core\Support\Context;

/**
 * Base Query class for CQRS pattern.
 * 
 * Queries represent read operations (Get, Find, List).
 * They are question-based and named with nouns (GetTodos, FindUser).
 * 
 * Queries should be:
 * - Immutable
 * - Read-only (never modify state)
 * - Execute synchronously
 * - Return data (DTOs, Read Models)
 * 
 */
abstract class Query
{
    protected string $boundedContext;
    protected string $correlationId;
    protected int $timestamp;

    public function __construct(string $boundedContext)
    {
        $this->boundedContext = $boundedContext;
        
        // Get correlation ID from context, or generate new one
        $this->correlationId = Context::getCorrelationId() 
            ?? Context::generateCorrelationId();
        
        // Store in context for this query execution
        Context::setCorrelationId($this->correlationId);
        
        $this->timestamp = time();
    }

    public function getBoundedContext(): string
    {
        return $this->boundedContext;
    }

    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * Get the query name.
     */
    public function getQueryName(): string
    {
        $reflection = new \ReflectionClass($this);
        return $reflection->getShortName();
    }

    /**
     * Convert query to array for logging.
     */
    public function toArray(): array
    {
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        
        $data = [
            '__class' => get_class($this),
            '__correlationId' => $this->correlationId,
            '__boundedContext' => $this->boundedContext,
        ];

        foreach ($properties as $property) {
            if ($property->isPublic()) {
                $data[$property->getName()] = $property->getValue($this);
            }
        }

        return $data;
    }
}
