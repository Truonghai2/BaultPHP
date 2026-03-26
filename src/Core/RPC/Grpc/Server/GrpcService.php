<?php

namespace Core\RPC\Grpc\Server;

use Core\CQRS\{CommandBus, QueryBus};
use Core\Support\Result;

/**
 * Base class for gRPC services.
 * 
 * Provides integration with CQRS CommandBus and QueryBus.
 */
abstract class GrpcService
{
    public function __construct(
        protected CommandBus $commandBus,
        protected QueryBus $queryBus
    ) {}

    /**
     * Execute a command and convert Result to gRPC response.
     */
    protected function executeCommand($command): Result
    {
        return $this->commandBus->execute($command);
    }

    /**
     * Execute a query and convert Result to gRPC response.
     */
    protected function executeQuery($query): Result
    {
        return $this->queryBus->execute($query);
    }

    /**
     * Convert Result to gRPC response.
     * 
     * @throws \RuntimeException if Result is failure
     */
    protected function resultToResponse(Result $result, callable $transformer)
    {
        return $result->match(
            success: fn($data) => $transformer($data),
            failure: function($error) {
                // Convert to gRPC error
                throw new \RuntimeException($error);
            }
        );
    }

    /**
     * Handle gRPC error gracefully.
     */
    protected function handleError(\Throwable $e): never
    {
        // Map exceptions to gRPC status codes
        $message = $e->getMessage();
        
        // You can add custom exception mapping here
        if ($e instanceof \InvalidArgumentException) {
            throw new \RuntimeException($message, 3); // INVALID_ARGUMENT
        }
        
        if (str_contains($message, 'not found')) {
            throw new \RuntimeException($message, 5); // NOT_FOUND
        }
        
        throw new \RuntimeException($message, 13); // INTERNAL
    }
}
