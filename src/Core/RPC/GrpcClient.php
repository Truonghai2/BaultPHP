<?php

declare(strict_types=1);

namespace Core\RPC;

use Core\Application;
use Core\Support\Facades\Log;
use Grpc\BaseStub;
use Grpc\ChannelCredentials;
use InvalidArgumentException;
use RuntimeException;

/**
 * gRPC Client
 *
 * High-level client for making gRPC calls to remote services.
 * Supports connection pooling, retries, and error handling.
 */
class GrpcClient
{
    protected array $channels = [];
    protected array $stubs = [];
    protected array $config = [];

    public function __construct(
        Application $app,
        array $config = [],
    ) {
        $this->config = array_merge(config('grpc.client', []), $config);
    }

    /**
     * Call a gRPC service method
     *
     * @param string $service Service name (e.g., 'user.UserService')
     * @param string $method Method name (e.g., 'GetUser')
     * @param mixed $request Request message
     * @param array $options Call options
     * @return mixed Response message
     */
    public function call(string $service, string $method, mixed $request, array $options = []): mixed
    {
        $startTime = microtime(true);

        try {
            $stub = $this->getStub($service);
            $fullMethod = "/{$service}/{$method}";

            // Merge default options
            $callOptions = array_merge($this->getDefaultOptions(), $options);

            // Make the call
            [$response, $status] = $stub->{$method}($request, $callOptions)->wait();

            if ($status->code !== \Grpc\STATUS_OK) {
                throw new GrpcException(
                    "gRPC call failed: {$status->details}",
                    $status->code,
                    $status->metadata ?? []
                );
            }

            $executionTime = (microtime(true) - $startTime) * 1000;
            Log::debug("gRPC call completed", [
                'service' => $service,
                'method' => $method,
                'execution_time_ms' => round($executionTime, 2),
            ]);

            return $response;

        } catch (\Throwable $e) {
            $executionTime = (microtime(true) - $startTime) * 1000;
            Log::error("gRPC call failed", [
                'service' => $service,
                'method' => $method,
                'error' => $e->getMessage(),
                'execution_time_ms' => round($executionTime, 2),
            ]);

            throw $e;
        }
    }

    /**
     * Get or create gRPC stub for service
     *
     * @param string $service Service name
     * @return BaseStub
     */
    protected function getStub(string $service): BaseStub
    {
        if (isset($this->stubs[$service])) {
            return $this->stubs[$service];
        }

        $serviceConfig = $this->getServiceConfig($service);
        $stubClass = $serviceConfig['stub_class'] ?? null;

        if (!$stubClass || !class_exists($stubClass)) {
            throw new InvalidArgumentException("Stub class not found for service: {$service}. Make sure to compile .proto files first.");
        }

        $address = $serviceConfig['host'] . ':' . $serviceConfig['port'];
        $options = [
            'credentials' => $this->getCredentials($serviceConfig),
        ];

        if (isset($serviceConfig['timeout'])) {
            $options['timeout'] = $serviceConfig['timeout'] * 1000000; // Convert to microseconds
        }

        $stub = new $stubClass($address, $options);

        $this->stubs[$service] = $stub;

        return $stub;
    }

    /**
     * Get or create gRPC channel (for future use with connection pooling)
     *
     * @param string $host
     * @param int $port
     * @param array $config
     * @return string Channel key
     */
    protected function getChannel(string $host, int $port, array $config): string
    {
        $key = "{$host}:{$port}";
        
        // Channels are managed by gRPC library internally
        // We just track the key for reference
        if (!isset($this->channels[$key])) {
            $this->channels[$key] = $key;
        }

        return $key;
    }

    /**
     * Get credentials for connection
     *
     * @param array $config
     * @return \Grpc\ChannelCredentials
     */
    protected function getCredentials(array $config): \Grpc\ChannelCredentials
    {
        $secure = $config['secure'] ?? false;

        if ($secure) {
            $rootCert = $config['root_cert'] ?? null;
            if ($rootCert) {
                return ChannelCredentials::createSsl($rootCert);
            }
            return ChannelCredentials::createSsl();
        }

        return ChannelCredentials::createInsecure();
    }

    /**
     * Get service configuration
     *
     * @param string $service
     * @return array
     */
    protected function getServiceConfig(string $service): array
    {
        $services = $this->config['services'] ?? [];

        if (!isset($services[$service])) {
            throw new InvalidArgumentException("Service not configured: {$service}");
        }

        return $services[$service];
    }

    /**
     * Get default call options
     *
     * @return array
     */
    protected function getDefaultOptions(): array
    {
        return [
            'timeout' => $this->config['timeout'] ?? 30 * 1000000, // microseconds
            'retry' => $this->config['retry'] ?? false,
        ];
    }

    /**
     * Register a service configuration
     *
     * @param string $service
     * @param array $config
     */
    public function registerService(string $service, array $config): void
    {
        $this->config['services'][$service] = $config;
    }

    /**
     * Close all channels
     */
    public function close(): void
    {
        // Channels are managed by gRPC library
        // Clear stub references
        $this->stubs = [];
        $this->channels = [];
    }
}
