<?php

namespace Core\RPC\Grpc\Middleware;

use Psr\Log\LoggerInterface;

/**
 * gRPC Logging Middleware.
 * 
 * Logs all gRPC requests and responses.
 */
class LoggingMiddleware
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function __invoke($request, callable $next)
    {
        $startTime = microtime(true);
        
        $this->logger->info('gRPC request started', [
            'method' => $request->method ?? 'unknown',
            'data' => $this->sanitize($request)
        ]);
        
        try {
            $response = $next($request);
            
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->info('gRPC request completed', [
                'method' => $request->method ?? 'unknown',
                'duration_ms' => round($duration, 2)
            ]);
            
            return $response;
            
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->error('gRPC request failed', [
                'method' => $request->method ?? 'unknown',
                'error' => $e->getMessage(),
                'duration_ms' => round($duration, 2)
            ]);
            
            throw $e;
        }
    }
    
    private function sanitize($data): array
    {
        // Remove sensitive data like passwords
        $sanitized = (array) $data;
        
        if (isset($sanitized['password'])) {
            $sanitized['password'] = '***';
        }
        
        return $sanitized;
    }
}
