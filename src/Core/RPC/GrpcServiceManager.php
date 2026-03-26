<?php

declare(strict_types=1);

namespace Core\RPC;

use Core\Application;
use Core\Support\Facades\Log;

/**
 * gRPC Service Manager
 *
 * Manages gRPC services, clients, and servers.
 * Provides a centralized way to interact with gRPC infrastructure.
 */
class GrpcServiceManager
{
    protected GrpcClient $client;
    protected ?GrpcServer $server = null;
    protected ProtobufCompiler $compiler;
    protected array $config = [];

    public function __construct(
        Application $app,
        ?GrpcClient $client = null,
        ?ProtobufCompiler $compiler = null,
    ) {
        $this->config = config('grpc', []);
        $this->client = $client ?? $app->make(GrpcClient::class);
        $this->compiler = $compiler ?? new ProtobufCompiler($this->config['protobuf'] ?? []);
    }

    /**
     * Get gRPC client
     *
     * @return GrpcClient
     */
    public function getClient(): GrpcClient
    {
        return $this->client;
    }

    /**
     * Get gRPC server
     *
     * @return GrpcServer
     */
    public function getServer(): GrpcServer
    {
        if ($this->server === null) {
            $this->server = app(GrpcServer::class);
        }

        return $this->server;
    }

    /**
     * Get Protobuf compiler
     *
     * @return ProtobufCompiler
     */
    public function getCompiler(): ProtobufCompiler
    {
        return $this->compiler;
    }

    /**
     * Call a gRPC service method
     *
     * @param string $service
     * @param string $method
     * @param mixed $request
     * @param array $options
     * @return mixed
     */
    public function call(string $service, string $method, mixed $request, array $options = []): mixed
    {
        return $this->client->call($service, $method, $request, $options);
    }

    /**
     * Register a service on the server
     *
     * @param string $serviceName
     * @param object $serviceImpl
     */
    public function registerService(string $serviceName, object $serviceImpl): void
    {
        $this->getServer()->registerService($serviceName, $serviceImpl);
    }

    /**
     * Compile proto files
     *
     * @param string $protoFile
     * @param string $outputDir
     * @param array $options
     * @return bool
     */
    public function compileProto(string $protoFile, string $outputDir, array $options = []): bool
    {
        return $this->compiler->compile($protoFile, $outputDir, $options);
    }

    /**
     * Check if gRPC is available
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return extension_loaded('grpc');
    }

    /**
     * Check if protoc is available
     *
     * @return bool
     */
    public function isProtocAvailable(): bool
    {
        return $this->compiler->isAvailable();
    }
}
