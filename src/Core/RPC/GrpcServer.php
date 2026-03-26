<?php

declare(strict_types=1);

namespace Core\RPC;

use Core\Application;
use Core\Support\Facades\Log;
use Grpc\Server;
use Grpc\ServerCredentials;
use InvalidArgumentException;

/**
 * gRPC Server
 *
 * Server for exposing gRPC services.
 * Handles incoming gRPC requests and routes them to appropriate handlers.
 */
class GrpcServer
{
    protected Server $server;
    protected array $services = [];
    protected array $config = [];
    protected bool $running = false;

    public function __construct(
        Application $app,
        array $config = [],
    ) {
        $this->config = array_merge(config('grpc.server', []), $config);
        $this->server = new Server();
    }

    /**
     * Register a gRPC service
     *
     * @param string $serviceName Full service name (e.g., 'user.UserService')
     * @param object $serviceImpl Service implementation
     */
    public function registerService(string $serviceName, object $serviceImpl): void
    {
        // For gRPC PHP, we need to register methods individually
        // The service implementation should have methods matching the RPC methods
        $this->services[$serviceName] = $serviceImpl;

        Log::info("gRPC service registered", ['service' => $serviceName]);
    }

    /**
     * Register multiple services from configuration
     *
     * @param array $services Array of ['service_name' => 'implementation_class']
     */
    public function registerServices(array $services): void
    {
        foreach ($services as $serviceName => $serviceClass) {
            if (!class_exists($serviceClass)) {
                throw new InvalidArgumentException("Service class not found: {$serviceClass}");
            }

            $serviceImpl = app($serviceClass);
            $this->registerService($serviceName, $serviceImpl);
        }
    }

    /**
     * Start the gRPC server
     *
     * @param string $host
     * @param int $port
     * @param array $options
     */
    public function start(string $host = '0.0.0.0', int $port = 50051, array $options = []): void
    {
        if ($this->running) {
            throw new \RuntimeException('gRPC server is already running');
        }

        $address = "{$host}:{$port}";
        $credentials = $this->getCredentials($options);

        // Register services with the server
        foreach ($this->services as $serviceName => $serviceImpl) {
            // Register service methods
            // Note: This is a simplified implementation
            // In production, you would use generated service classes
            $this->registerServiceMethods($serviceName, $serviceImpl);
        }

        $this->server->addHttp2Port($address, $credentials);

        Log::info("gRPC server starting", [
            'address' => $address,
            'services' => array_keys($this->services),
        ]);

        $this->server->start();
        $this->running = true;

        Log::info("gRPC server started", ['address' => $address]);
    }

    /**
     * Register service methods
     *
     * @param string $serviceName
     * @param object $serviceImpl
     */
    protected function registerServiceMethods(string $serviceName, object $serviceImpl): void
    {
        // This is a placeholder for service method registration
        // In practice, you would use generated service classes from protoc
        // For now, we'll use a generic handler approach
        $reflection = new \ReflectionClass($serviceImpl);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if (str_starts_with($method->getName(), '__')) {
                continue; // Skip magic methods
            }

            $fullMethodName = "/{$serviceName}/{$method->getName()}";
            $this->server->addGenericMethod($fullMethodName, [$serviceImpl, $method->getName()]);
        }
    }

    /**
     * Stop the gRPC server
     */
    public function stop(): void
    {
        if (!$this->running) {
            return;
        }

        $this->server->wait();
        $this->running = false;

        Log::info("gRPC server stopped");
    }

    /**
     * Get server credentials
     *
     * @param array $options
     * @return ServerCredentials|null
     */
    protected function getCredentials(array $options): ?ServerCredentials
    {
        $secure = $options['secure'] ?? $this->config['secure'] ?? false;

        if (!$secure) {
            return ServerCredentials::createInsecure();
        }

        $cert = $options['cert'] ?? $this->config['cert'] ?? null;
        $key = $options['key'] ?? $this->config['key'] ?? null;

        if (!$cert || !$key) {
            throw new InvalidArgumentException('Certificate and key required for secure gRPC server');
        }

        return ServerCredentials::createSsl(
            file_get_contents($cert),
            [
                'key' => file_get_contents($key),
            ]
        );
    }

    /**
     * Check if server is running
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Get registered services
     *
     * @return array
     */
    public function getServices(): array
    {
        return array_keys($this->services);
    }
}
