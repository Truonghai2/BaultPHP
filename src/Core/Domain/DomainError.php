<?php

namespace Core\Domain;

/**
 * Domain Error Base Class.
 * 
 * Represents a domain-level error (business rule violation).
 * These are different from technical errors (exceptions).
 */
abstract class DomainError extends \RuntimeException
{
    protected string $errorCode;
    protected array $context = [];

    /**
     * Create a domain error.
     * 
     * @param string $message Human-readable error message
     * @param string $errorCode Machine-readable error code
     * @param array $context Additional context
     */
    public function __construct(
        string $message,
        string $errorCode = '',
        array $context = []
    ) {
        parent::__construct($message);
        
        $this->errorCode = $errorCode ?: $this->getDefaultErrorCode();
        $this->context = $context;
    }

    /**
     * Get the error code.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Get the error context.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Convert to array for API responses.
     */
    public function toArray(): array
    {
        return [
            'error' => $this->errorCode,
            'message' => $this->getMessage(),
            'context' => $this->context,
        ];
    }

    /**
     * Get default error code based on class name.
     */
    protected function getDefaultErrorCode(): string
    {
        $className = class_basename(static::class);
        
        // Convert CamelCase to snake_case
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', 
            str_replace('Error', '', $className)
        ));
    }
}
