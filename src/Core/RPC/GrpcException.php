<?php

declare(strict_types=1);

namespace Core\RPC;

use RuntimeException;

/**
 * gRPC Exception
 *
 * Custom exception for gRPC-related errors.
 */
class GrpcException extends RuntimeException
{
    protected int $grpcCode;
    protected array $metadata;

    public function __construct(
        string $message = '',
        int $grpcCode = 0,
        array $metadata = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
        $this->grpcCode = $grpcCode;
        $this->metadata = $metadata;
    }

    /**
     * Get gRPC status code
     */
    public function getGrpcCode(): int
    {
        return $this->grpcCode;
    }

    /**
     * Get gRPC metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Check if error is retryable
     */
    public function isRetryable(): bool
    {
        $retryableCodes = [
            \Grpc\STATUS_UNAVAILABLE,
            \Grpc\STATUS_DEADLINE_EXCEEDED,
            \Grpc\STATUS_RESOURCE_EXHAUSTED,
            \Grpc\STATUS_ABORTED,
            \Grpc\STATUS_INTERNAL,
        ];

        return in_array($this->grpcCode, $retryableCodes, true);
    }
}
