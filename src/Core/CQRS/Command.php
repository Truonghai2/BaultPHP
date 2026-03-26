<?php

namespace Core\CQRS;

use Core\Support\Context;

/**
 * Base Command class for CQRS pattern.
 * 
 * Commands represent write operations (Create, Update, Delete).
 * They are task-based and named with verbs (AddTodo, CompleteTodo).
 * 
 * Commands should be:
 * - Immutable
 * - Contain all data needed for execution
 * - Execute asynchronously
 * - Return void or Result<void>
 * 
 * Example:
 * class AddTodoCommand extends Command {
 *     public function __construct(
 *         public readonly string $title,
 *         public readonly string $userId
 *     ) {
 *         parent::__construct('Todo');
 *     }
 * }
 * 
 * @see docs/CQRS_IMPLEMENTATION_GUIDE.md
 */
abstract class Command
{
    protected string $boundedContext;
    protected string $correlationId;
    protected array $metadata = [];
    protected int $timestamp;

    public function __construct(string $boundedContext)
    {
        $this->boundedContext = $boundedContext;
        
        // Get correlation ID from context, or generate new one
        $this->correlationId = Context::getCorrelationId() 
            ?? Context::generateCorrelationId();
        
        // Store in context for this command execution
        Context::setCorrelationId($this->correlationId);
        
        $this->timestamp = time();
        
        // Store metadata
        $this->metadata = [
            'timestamp' => $this->timestamp,
            'user_id' => $this->getCurrentUserId() ?? Context::getUserId(),
            'ip_address' => $this->getCurrentIpAddress(),
        ];
    }

    public function getBoundedContext(): string
    {
        return $this->boundedContext;
    }

    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * Get the command name.
     */
    public function getCommandName(): string
    {
        $reflection = new \ReflectionClass($this);
        return $reflection->getShortName();
    }

    /**
     * Convert command to array for serialization.
     */
    public function toArray(): array
    {
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        
        $data = [
            '__class' => get_class($this),
            '__correlationId' => $this->correlationId,
            '__boundedContext' => $this->boundedContext,
            '__metadata' => $this->metadata,
        ];

        foreach ($properties as $property) {
            if ($property->isPublic()) {
                $data[$property->getName()] = $property->getValue($this);
            }
        }

        return $data;
    }

    /**
     * Get current user ID from auth context.
     */
    protected function getCurrentUserId(): ?string
    {
        try {
            return app('auth')->id();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get current IP address from request.
     */
    protected function getCurrentIpAddress(): ?string
    {
        try {
            $request = app('request');
            return $request ? $request->ip() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
