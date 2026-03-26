<?php

namespace Core\RPC\Grpc\Server;

use Swoole\Http\Server as HttpServer;
use Psr\Log\LoggerInterface;

/**
 * gRPC Server for BaultFrame.
 * 
 * Runs alongside HTTP server on a separate port.
 * Integrates with CQRS CommandBus and QueryBus.
 */
class GrpcServer
{
    private array $services = [];
    private array $middleware = [];

    public function __construct(
        private string $host = '0.0.0.0',
        private int $port = 50051,
        private LoggerInterface $logger,
        private array $options = []
    ) {}

    /**
     * Register a gRPC service.
     * 
     * @param string $serviceClass Fully qualified service class
     * @param object $implementation Service implementation
     */
    public function registerService(string $serviceClass, object $implementation): void
    {
        $this->services[$serviceClass] = $implementation;
        $this->logger->info("Registered gRPC service: $serviceClass");
    }

    /**
     * Add middleware to the gRPC pipeline.
     */
    public function addMiddleware(callable $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Start the gRPC server.
     */
    public function start(): void
    {
        $server = new HttpServer($this->host, $this->port, SWOOLE_PROCESS);

        // Configure server
        $server->set([
            'worker_num' => $this->options['worker_num'] ?? 4,
            'enable_coroutine' => true,
            'open_http2_protocol' => true, // Required for gRPC
        ]);

        // Handle gRPC requests
        $server->on('request', function ($request, $response) {
            $this->handleRequest($request, $response);
        });

        $this->logger->info("gRPC server starting on {$this->host}:{$this->port}");
        
        $server->start();
    }

    /**
     * Handle incoming gRPC request.
     */
    private function handleRequest($request, $response): void
    {
        try {
            // Parse gRPC request
            $path = $request->server['request_uri'];
            $method = $this->parseGrpcMethod($path);

            // Find service handler
            $handler = $this->findHandler($method['service'], $method['method']);

            if (!$handler) {
                $this->sendError($response, 'Service not found', 12); // UNIMPLEMENTED
                return;
            }

            // Decode protobuf message
            $requestData = $this->decodeProtobuf($request->rawContent(), $method);

            // Execute middleware pipeline
            $result = $this->executeMiddleware($handler, $requestData);

            // Encode and send response
            $this->sendSuccess($response, $result);

        } catch (\Throwable $e) {
            $this->logger->error('gRPC request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->sendError($response, $e->getMessage(), 13); // INTERNAL
        }
    }

    /**
     * Parse gRPC method from path.
     * 
     * Path format: /package.Service/Method
     */
    private function parseGrpcMethod(string $path): array
    {
        $parts = explode('/', trim($path, '/'));
        $serviceParts = explode('.', $parts[0]);
        
        return [
            'package' => implode('.', array_slice($serviceParts, 0, -1)),
            'service' => end($serviceParts),
            'method' => $parts[1] ?? '',
            'full' => $path
        ];
    }

    /**
     * Find handler for service method.
     */
    private function findHandler(string $service, string $method): ?callable
    {
        foreach ($this->services as $serviceClass => $implementation) {
            $className = class_basename($serviceClass);
            
            if ($className === $service) {
                $methodName = lcfirst($method);
                
                if (method_exists($implementation, $methodName)) {
                    return [$implementation, $methodName];
                }
            }
        }

        return null;
    }

    /**
     * Execute middleware pipeline.
     */
    private function executeMiddleware(callable $handler, $request)
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn($next, $middleware) => fn($req) => $middleware($req, $next),
            $handler
        );

        return $pipeline($request);
    }

    /**
     * Decode protobuf message.
     */
    private function decodeProtobuf(string $data, array $method)
    {
        // Extract gRPC frame (5 bytes header + protobuf data)
        if (strlen($data) < 5) {
            throw new \RuntimeException('Invalid gRPC frame');
        }

        $compressed = ord($data[0]);
        $length = unpack('N', substr($data, 1, 4))[1];
        $protobufData = substr($data, 5, $length);

        // Decode using generated message class
        // This requires grpc/grpc and google/protobuf packages
        return $protobufData; // Placeholder - actual decoding happens in service
    }

    /**
     * Send successful response.
     */
    private function sendSuccess($response, $data): void
    {
        $response->header('content-type', 'application/grpc+proto');
        $response->header('grpc-status', '0'); // OK
        
        // Encode protobuf response
        $encoded = $this->encodeProtobuf($data);
        
        $response->end($encoded);
    }

    /**
     * Send error response.
     */
    private function sendError($response, string $message, int $code): void
    {
        $response->header('content-type', 'application/grpc+proto');
        $response->header('grpc-status', (string) $code);
        $response->header('grpc-message', $message);
        
        $response->end();
    }

    /**
     * Encode protobuf message to gRPC frame.
     */
    private function encodeProtobuf($data): string
    {
        if (is_string($data)) {
            $protobufData = $data;
        } else {
            // Serialize protobuf message
            $protobufData = $data->serializeToString();
        }

        // gRPC frame: 1 byte compressed flag + 4 bytes length + data
        $compressed = 0;
        $length = pack('N', strlen($protobufData));
        
        return chr($compressed) . $length . $protobufData;
    }
}
