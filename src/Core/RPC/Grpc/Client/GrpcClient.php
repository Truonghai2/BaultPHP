<?php

namespace Core\RPC\Grpc\Client;

use Grpc\ChannelCredentials;
use Psr\Log\LoggerInterface;

/**
 * gRPC Client for BaultFrame.
 * 
 * Provides easy-to-use interface for calling gRPC services.
 */
class GrpcClient
{
    private array $channels = [];

    public function __construct(
        private LoggerInterface $logger,
        private array $defaultOptions = []
    ) {}

    /**
     * Create a service client.
     * 
     * @param string $serviceClass Generated gRPC client class
     * @param string $hostname gRPC server address
     * @param array $options Client options
     * @return mixed Service client instance
     */
    public function createClient(
        string $serviceClass,
        string $hostname = 'localhost:50051',
        array $options = []
    ) {
        $key = $hostname . '|' . $serviceClass;

        if (isset($this->channels[$key])) {
            return $this->channels[$key];
        }

        $options = array_merge($this->defaultOptions, $options);

        // Create credentials
        $credentials = $options['secure'] ?? false
            ? ChannelCredentials::createSsl()
            : ChannelCredentials::createInsecure();

        // Create client
        $client = new $serviceClass($hostname, [
            'credentials' => $credentials,
            'timeout' => $options['timeout'] ?? 10000000, // 10 seconds
        ]);

        $this->channels[$key] = $client;

        $this->logger->info("Created gRPC client: $serviceClass @ $hostname");

        return $client;
    }

    /**
     * Call gRPC method with automatic retry and logging.
     */
    public function call(
        $client,
        string $method,
        $request,
        array $metadata = [],
        int $retries = 3
    ) {
        $attempt = 0;

        while ($attempt < $retries) {
            try {
                $this->logger->debug("Calling gRPC method: $method", [
                    'attempt' => $attempt + 1
                ]);

                // Call the method
                list($response, $status) = $client->$method($request, $metadata)->wait();

                // Check status
                if ($status->code !== \Grpc\STATUS_OK) {
                    throw new \RuntimeException(
                        "gRPC call failed: {$status->details}",
                        $status->code
                    );
                }

                $this->logger->debug("gRPC call succeeded: $method");

                return $response;

            } catch (\Throwable $e) {
                $attempt++;

                if ($attempt >= $retries) {
                    $this->logger->error("gRPC call failed after $retries attempts", [
                        'method' => $method,
                        'error' => $e->getMessage()
                    ]);

                    throw $e;
                }

                // Exponential backoff
                usleep(100000 * pow(2, $attempt)); // 100ms, 200ms, 400ms...
            }
        }
    }

    /**
     * Create metadata with authentication token.
     */
    public function withAuth(string $token): array
    {
        return [
            'authorization' => ['Bearer ' . $token]
        ];
    }
}
