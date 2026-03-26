<?php

namespace Core\Server;

use Core\Application;
use Core\Contracts\Exceptions\Handler as ExceptionHandler;
use Core\Contracts\Http\Kernel as HttpKernel;
use Core\Contracts\Session\SessionInterface;
use Core\Exceptions\ServiceUnavailableException;
use Core\Foundation\StateResetter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Throwable;

/**
 * Encapsulates the entire lifecycle of a single HTTP request within the Swoole server.
 *
 * This class is responsible for transforming the request, passing it through the application kernel,
 * handling exceptions, sending the response, and cleaning up state. This separation of concerns
 * makes the SwooleServer class cleaner and the request handling logic more testable.
 */
final class RequestLifecycle
{
    private string $requestId;
    private float $startTime;
    private ?ResponseInterface $response = null;
    private ?float $endTime = null;

    /**
     * Static request counter for periodic cleanup (shared across instances in same worker)
     */
    private static int $requestCount = 0;

    /**
     * Memory usage threshold for warnings (100MB)
     */
    private const MEMORY_WARNING_THRESHOLD = 100 * 1024 * 1024;

    /**
     * Interval for periodic garbage collection (every 100 requests)
     */
    private const GC_INTERVAL = 100;

    public function __construct(
        private Application $app,
        private HttpKernel $kernel,
        private ExceptionHandler $exceptionHandler,
        private StateResetter $stateResetter,
        private SwoolePsr7Bridge $psr7Bridge,
        private bool $isDebug,
    ) {
        $this->startTime = microtime(true);
        $this->requestId = uniqid();
        
        // Store request context in coroutine
        if (extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0) {
            CoroutineContext::set('request_id', $this->requestId);
            CoroutineContext::set('start_time', $this->startTime);
        }
    }

    /**
     * The main entry point for handling a request.
     */
    public function handle(SwooleRequest $swooleRequest, SwooleResponse $swooleResponse): void
    {
        // Increment request counter for periodic cleanup
        self::$requestCount++;

        try {
            $this->initialize($swooleRequest);
            $psr7Request = $this->transformRequest($swooleRequest);
            $this->response = $this->executeKernel($psr7Request);
        } catch (Throwable $e) {
            $psr7Request = $this->psr7Bridge->toPsr7Request($swooleRequest);
            try {
                $this->response = $this->handleException($psr7Request, $e);
            } catch (Throwable $e2) {
                $this->response = $this->fallbackExceptionResponse($e2);
            }
            if ($this->response === null) {
                $this->response = $this->fallbackExceptionResponse(new \RuntimeException('No response from exception handler'));
            }
            $this->response = $this->finalizeResponse($this->response);
            $this->sendResponse($this->response, $swooleResponse);
            $this->terminate();
        }
    }

    /**
     * Sets up the application container for the incoming request.
     */
    private function initialize(SwooleRequest $swooleRequest): void
    {
        $this->app->instance('request_id', $this->requestId);
        $this->app->instance(SwooleRequest::class, $swooleRequest);
    }

    /**
     * Transforms the Swoole request to a PSR-7 request and binds it.
     */
    private function transformRequest(SwooleRequest $swooleRequest): ServerRequestInterface
    {
        $psr7Request = $this->psr7Bridge->toPsr7Request($swooleRequest);
        $this->app->instance(ServerRequestInterface::class, $psr7Request);
        return $psr7Request;
    }

    /**
     * Executes the application kernel and handles exceptions.
     */
    private function executeKernel(ServerRequestInterface $psr7Request): ResponseInterface
    {
        try {
            return $this->kernel->handle($psr7Request);
        } catch (Throwable $e) {
            return $this->handleException($psr7Request, $e);
        }
    }

    /**
     * Handles an exception by reporting it and rendering it to a response.
     */
    private function handleException(ServerRequestInterface $request, Throwable $e): ResponseInterface
    {
        $this->exceptionHandler->report($request, $e);

        if ($e instanceof ServiceUnavailableException) {
            $this->getLogger()->warning("Request [{$this->requestId}]: Service unavailable, circuit breaker is likely open.", ['exception' => $e->getMessage()]);
        }

        return $this->exceptionHandler->render($request, $e);
    }

    /**
     * Adds final touches to the response before sending.
     * Accepts null when exception handling failed; returns a minimal 500 response.
     */
    private function finalizeResponse(?ResponseInterface $response): ResponseInterface
    {
        if ($response === null) {
            $response = $this->fallbackExceptionResponse(new \RuntimeException('No response produced'));
        }
        $response = $response->withHeader('X-Request-ID', $this->requestId);

        if ($this->isDebug) {
            $response = $response->withHeader('X-Debug-ID', $this->requestId);
        }

        return $response;
    }

    /**
     * Returns a minimal 500 response when exception handler fails (e.g. circular dependency).
     */
    private function fallbackExceptionResponse(Throwable $e): ResponseInterface
    {
        $body = '<!DOCTYPE html><html><body><h1>500 Server Error</h1><p>An error occurred.</p></body></html>';
        if ($this->isDebug) {
            $body = '<!DOCTYPE html><html><body><h1>500 Server Error</h1><pre>' . htmlspecialchars((string) $e) . '</pre></body></html>';
        }
        return new \Nyholm\Psr7\Response(500, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
    }

    /**
     * Sends the final PSR-7 response to the client via the Swoole response object.
     * Optimized for performance - minimal checks and fast path for production.
     */
    private function sendResponse(ResponseInterface $response, SwooleResponse $swooleResponse): void
    {
        $request = null;
        $shouldLog = $this->isDebug || config('app.log_requests', false);
        
        // Only resolve request if logging is needed (performance optimization)
        if ($shouldLog) {
            // Fast path: Check if already resolved (most common case)
            if ($this->app->resolved(ServerRequestInterface::class)) {
                try {
                    $request = $this->app->make(ServerRequestInterface::class);
                } catch (\Throwable $e) {
                    // Silently fail if request can't be resolved for logging
                    $request = null;
                }
            }
        }

        // Log request if needed (optimized path)
        if ($shouldLog && $request !== null) {
            $this->logRequest($request, $response);
        }

        // Send response (this is the critical path - must be fast)
        $this->psr7Bridge->toSwooleResponse($response, $swooleResponse, $request);
    }

    /**
     * Logs the request/response. Separated for better performance and testability.
     */
    private function logRequest(ServerRequestInterface $request, ResponseInterface $response): void
    {
        try {
            // Fast path: Try to get RequestLogger from container
            if ($this->app->bound(RequestLogger::class)) {
                $this->app->make(RequestLogger::class)->log(
                    $request,
                    $response,
                    $this->startTime,
                    $this->requestId,
                );
                return;
            }

            // Fallback: Create RequestLogger directly (rare case)
            if ($this->app->bound(\Psr\Log\LoggerInterface::class)) {
                $logger = $this->app->make(\Psr\Log\LoggerInterface::class);
                $requestLogger = new RequestLogger($this->app, $logger);
                $requestLogger->log($request, $response, $this->startTime, $this->requestId);
            }
        } catch (\Core\Exceptions\ContainerException $e) {
            // Silently skip logging if there's a circular dependency
            // This should not happen in normal operation
        } catch (\Throwable $e) {
            // Silently skip logging on any error to avoid breaking response
        }
    }

    /**
     * Performs all post-request cleanup tasks.
     * Includes memory monitoring and periodic garbage collection.
     */
    private function terminate(): void
    {
        $this->endTime = microtime(true);

        // Call terminating callbacks (password rehashing, etc.)
        // These run AFTER response is sent but BEFORE cleanup
        $this->app->callTerminatingCallbacks();

        $this->stateResetter->reset();

        // Clear request-specific container instances
        $this->app->forgetInstance(ServerRequestInterface::class);
        $this->app->forgetInstance(SwooleRequest::class);
        $this->app->forgetInstance('request_id');

        // Clear response instance if cached
        if ($this->app->bound(ResponseInterface::class)) {
            $this->app->forgetInstance(ResponseInterface::class);
        }

        // Clear coroutine context
        if (extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0) {
            CoroutineContext::clear();
        }

        // Memory management: Periodic garbage collection and monitoring
        $this->performMemoryManagement();
    }

    /**
     * Performs memory management tasks including periodic GC and monitoring.
     * Optimized for high throughput - minimal overhead in production.
     * Uses bitwise operations for faster modulo checks.
     */
    private function performMemoryManagement(): void
    {
        $requestCount = self::$requestCount;
        
        // Fast path for production: Only check memory threshold, skip GC in most cases
        if (!$this->isDebug) {
            // Periodic garbage collection (every N requests) - use bitwise check for speed
            if (($requestCount & (self::GC_INTERVAL - 1)) === 0) {
                gc_collect_cycles();
            }

            // Memory monitoring - only check threshold, no logging unless exceeded
            // Use bitwise check to reduce frequency (check every 10 requests instead of every request)
            if (($requestCount & 9) === 0) {
                $memoryUsage = memory_get_usage(true);
                if ($memoryUsage > self::MEMORY_WARNING_THRESHOLD) {
                    $this->getLogger()->warning('High memory usage detected', [
                        'request_id' => $this->requestId,
                        'memory_usage' => round($memoryUsage / 1024 / 1024, 2) . ' MB',
                        'request_count' => $requestCount,
                    ]);
                }
            }
            return;
        }

        // Debug mode: Full logging (only in development)
        if (($requestCount & (self::GC_INTERVAL - 1)) === 0) {
            $beforeGC = memory_get_usage(true);
            gc_collect_cycles();
            $afterGC = memory_get_usage(true);
            $freed = $beforeGC - $afterGC;

            if ($freed > 0) {
                $this->getLogger()->debug('Periodic garbage collection performed', [
                    'request_count' => $requestCount,
                    'memory_freed' => round($freed / 1024 / 1024, 2) . ' MB',
                    'memory_after_gc' => round($afterGC / 1024 / 1024, 2) . ' MB',
                ]);
            }
        }

        // Memory monitoring in debug mode - check every 10 requests
        if (($requestCount & 9) === 0) {
            $memoryUsage = memory_get_usage(true);
            $memoryPeak = memory_get_peak_usage(true);

            if ($memoryUsage > self::MEMORY_WARNING_THRESHOLD) {
                $this->getLogger()->warning('High memory usage detected', [
                    'request_id' => $this->requestId,
                    'memory_usage' => round($memoryUsage / 1024 / 1024, 2) . ' MB',
                    'memory_peak' => round($memoryPeak / 1024 / 1024, 2) . ' MB',
                    'request_count' => $requestCount,
                ]);
            }

            // Log memory stats in debug mode
            $this->getLogger()->debug('Memory stats', [
                'request_count' => $requestCount,
                'memory_usage' => round($memoryUsage / 1024 / 1024, 2) . ' MB',
                'memory_peak' => round($memoryPeak / 1024 / 1024, 2) . ' MB',
            ]);
        }
    }


    private function getLogger(): LoggerInterface
    {
        return $this->app->make(LoggerInterface::class);
    }
}
