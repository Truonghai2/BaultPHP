<?php

declare(strict_types=1);

namespace Core\RPC;

/**
 * Base Service Handler
 *
 * Base class for gRPC service implementations.
 * Provides common functionality for handling gRPC requests.
 */
abstract class ServiceHandler
{
    /**
     * Handle a gRPC request
     *
     * @param mixed $request Request message
     * @param \Grpc\ServerCallWriter $writer Response writer
     * @return void
     */
    abstract public function handle(mixed $request, \Grpc\ServerCallWriter $writer): void;

    /**
     * Send success response
     *
     * @param mixed $response Response message
     * @param \Grpc\ServerCallWriter $writer
     * @return void
     */
    protected function sendResponse(mixed $response, \Grpc\ServerCallWriter $writer): void
    {
        $writer->finish($response);
    }

    /**
     * Send error response
     *
     * @param int $code gRPC status code
     * @param string $message Error message
     * @param \Grpc\ServerCallWriter $writer
     * @return void
     */
    protected function sendError(int $code, string $message, \Grpc\ServerCallWriter $writer): void
    {
        $writer->finish(null, [
            'code' => $code,
            'details' => $message,
        ]);
    }

    /**
     * Send not found error
     *
     * @param string $message
     * @param \Grpc\ServerCallWriter $writer
     * @return void
     */
    protected function sendNotFound(string $message, \Grpc\ServerCallWriter $writer): void
    {
        $this->sendError(\Grpc\STATUS_NOT_FOUND, $message, $writer);
    }

    /**
     * Send invalid argument error
     *
     * @param string $message
     * @param \Grpc\ServerCallWriter $writer
     * @return void
     */
    protected function sendInvalidArgument(string $message, \Grpc\ServerCallWriter $writer): void
    {
        $this->sendError(\Grpc\STATUS_INVALID_ARGUMENT, $message, $writer);
    }
}
