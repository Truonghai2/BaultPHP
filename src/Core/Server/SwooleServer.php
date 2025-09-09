<?php

namespace Core\Server;

use Core\Application;
use Core\Contracts\Exceptions\Handler as ExceptionHandler;
use Core\Contracts\PoolManager;
use Core\Contracts\StatefulService;
use Core\Contracts\Task\Task;
use Core\Debug\DebugManager;
use Core\Exceptions\ServiceUnavailableException;
use Core\Queue\DelayedJobScheduler;
use Core\Server\Development\FileWatcher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop\Adapter\Swoole\SwooleDriver;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Http\Server as SwooleHttpServer;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Throwable;

class SwooleServer
{
    protected Application $app;
    protected SwooleHttpServer $server;
    protected array $config;
    protected ?int $delayedJobTimerId = null;
    protected SwoolePsr7Bridge $psr7Bridge;
    protected HttpFoundationFactory $httpFoundationFactory;
    protected \Core\Contracts\Http\Kernel $kernel;
    protected ExceptionHandler $exceptionHandler;
    protected ?FileWatcher $fileWatcher = null;
    protected bool $isDebug = false;
    /**
     * Bộ đếm số lượng request đã xử lý bởi worker.
     * Dùng để kích hoạt các tác vụ dọn dẹp định kỳ.
     * @var int
     */
    protected int $requestCount = 0;

    public function __construct(Application $app)
    {
        $this->app = $app;

        $this->config = config('server.swoole', []);

        $host = $this->config['host'] ?? '0.0.0.0';
        $port = $this->config['port'] ?? 9501;

        $this->server = new SwooleHttpServer($host, $port);

        $settings = $this->prepareServerSettings();
        $ignoredKeys = ['host', 'port', 'watch', 'db_pool', 'redis_pool', 'watch_file'];
        $validSwooleSettings = array_filter(
            $settings,
            fn ($key) => !in_array($key, $ignoredKeys, true),
            ARRAY_FILTER_USE_KEY,
        );

        $this->server->set($validSwooleSettings);
        $this->psr7Bridge = new SwoolePsr7Bridge();
        $this->httpFoundationFactory = new HttpFoundationFactory();

        $currentEnv = config('app.env', 'production');
        $isDevelopmentEnv = in_array($currentEnv, ['local', 'development'], true);
        $watchConfigExists = !empty($this->config['watch']['directories']);
        if ($isDevelopmentEnv && $watchConfigExists) {
            $this->fileWatcher = new FileWatcher($this->server, $this->config['watch'], $this->getLogger());
        }
    }

    /**
     * Cung cấp các cấu hình mặc định, được tối ưu cho hiệu năng và độ ổn định.
     * Người dùng có thể ghi đè các giá trị này trong config/server.php.
     *
     * @return array
     */
    private function getDefaultSettings(): array
    {
        $isProduction = config('app.env', 'production') === 'production';

        return [
            'worker_num' => swoole_cpu_num() * ($isProduction ? 2 : 1),
            'task_worker_num' => swoole_cpu_num(),
            'task_enable_coroutine' => true,

            'pid_file' => storage_path('logs/swoole.pid'),
            'log_file' => storage_path('logs/swoole.log'),
            'log_level' => $isProduction ? SWOOLE_LOG_WARNING : SWOOLE_LOG_INFO,

            'max_request' => 10000,
            'max_wait_time' => 30,
            'task_max_request' => 10000,
            'max_connection' => 10000,

            'open_tcp_nodelay' => true,

            'open_http2_protocol' => true,

            'buffer_output_size' => 32 * 1024 * 1024,

            'http_parse_post' => true,
            'upload_tmp_dir' => storage_path('app/uploads'),

            'watch' => [],
        ];
    }

    /**
     * Chuẩn bị mảng settings cuối cùng để truyền vào Swoole server.
     *
     * @return array
     */
    private function prepareServerSettings(): array
    {
        $settings = $this->getDefaultSettings();

        $settings = array_replace_recursive($settings, $this->config);

        $currentEnv = config('app.env', 'production');
        $isDevelopmentEnv = in_array($currentEnv, ['local', 'development'], true);
        $watchConfigExists = !empty($settings['watch']['directories']) && is_array($settings['watch']['directories']);

        $settings['daemonize'] = false;

        return $settings;
    }

    public function start(): void
    {
        $this->registerServerEvents();
        $this->server->start();
    }

    /**
     * Get statistics from the running Swoole server.
     *
     * @return array|false
     */
    public function stats(): array|false
    {
        return $this->server->stats();
    }

    /**
     * Đăng ký tất cả các callback sự kiện cho Swoole server.
     */
    protected function registerServerEvents(): void
    {
        $this->server->on('start', [$this, 'onStart']);
        $this->server->on('workerStart', [$this, 'onWorkerStart']);
        $this->server->on('shutdown', [$this, 'onShutdown']);
        $this->server->on('workerStop', [$this, 'onWorkerStop']);
        $this->server->on('workerExit', [$this, 'onWorkerExit']);
        $this->server->on('request', [$this, 'handleRequest']);
        $this->server->on('task', [$this, 'onTask']);
        $this->server->on('finish', [$this, 'onFinish']);
    }

    public function onStart(SwooleHttpServer $server): void
    {
        $this->getLogger()->info('Swoole HTTP server is started', ['url' => "http://{$server->host}:{$server->port}"]);
        $this->fileWatcher?->start();
    }

    /**
     * Called in the master process before the server shuts down.
     */
    public function onShutdown(SwooleHttpServer $server): void
    {
        $this->fileWatcher?->stop();
        $this->getLogger()->info('Swoole HTTP server is shutting down gracefully.');
    }

    public function onWorkerExit(SwooleHttpServer $server, int $workerId): void
    {
        $this->getLogger()->warning("Swoole Worker #{$workerId} exited unexpectedly.", ['worker_id' => $workerId]);
    }

    public function handleRequest(SwooleRequest $swooleRequest, SwooleResponse $swooleResponse): void
    {
        $startTime = microtime(true);
        $requestId = bin2hex(random_bytes(4));
        try {
            $this->app->instance('request_id', $requestId);

            $this->app->instance(SwooleRequest::class, $swooleRequest);

            /** @var DebugManager $debugManager */
            if ($this->app->bound(DebugManager::class)) {
                $this->app->make(DebugManager::class)->enable();
            }

            if ($this->isDebug) {
                $this->getLogger()->debug("Request [{$requestId}] received.", ['method' => $swooleRequest->getMethod(), 'uri' => $swooleRequest->server['request_uri']]);
            }

            $psr7Request = $this->transformRequest($swooleRequest);

            $this->app->instance(ServerRequestInterface::class, $psr7Request);

            try {
                $response = $this->kernel->handle($psr7Request);
            } catch (ServiceUnavailableException $e) {
                $response = $this->exceptionHandler->render($psr7Request, $e);
                $this->getLogger()->warning("Request [{$requestId}]: Service unavailable, circuit breaker is likely open.", ['exception' => $e->getMessage()]);
            } catch (Throwable $e) {
                $this->exceptionHandler->report($e);
                $response = $this->exceptionHandler->render($psr7Request, $e);
                $this->getLogger()->error("Request [{$requestId}]: Unhandled exception caught.", ['exception' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            }

            $this->logRequest($psr7Request, $response, $startTime);

            $this->transformResponse($response, $swooleResponse);
        } finally {
            foreach ($this->app->getTagged(StatefulService::class) as $service) {
                $service->resetState();
            }

            if ($this->app->bound(DebugManager::class)) {
                /** @var DebugManager $debugManager */
                $debugManager = $this->app->make(DebugManager::class);
                if ($debugManager->isEnabled() && isset($response) && str_contains($response->getHeaderLine('Content-Type'), 'text/html')) {
                    try {
                        $configService = $this->app->make('config');

                        if (method_exists($configService, 'all')) {
                            $debugManager->recordConfig($configService->all());
                        }

                        $debugManager->recordRequestInfo([
                            'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
                            'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                        ]);

                        $debugData = $debugManager->getData();
                        /** @var \Core\Redis\FiberRedisManager $redisManager */
                        $redisManager = $this->app->make('redis'); // Resolves to FiberRedisManager
                        $redisClient = null;
                        try {
                            $redisClient = $redisManager->get('default');
                            $key = 'debug:requests:' . $requestId;
                            $redisClient->set($key, json_encode($debugData), config('debug.expiration', 3600));
                        } finally {
                            if ($redisClient) {
                                $redisManager->put($redisClient, 'default');
                            }
                        }
                    } catch (Throwable $e) {
                        $this->getLogger()->error('Failed to save debug data to Redis.', ['exception' => $e]);
                    }
                }
            }
            $this->cleanupAfterRequest($requestId);
        }
    }

    /**
     * Dispatch a task to a Swoole task worker.
     *
     * @param Task $task The task object to be executed. Must be serializable.
     * @return int|false The task ID if dispatched successfully, or false on failure.
     */
    public function dispatchTask(Task $task): int|false
    {
        return $this->server->task(serialize($task));
    }

    /**
     * Handle an incoming task in a task worker.
     * This method runs in a separate Task Worker process.
     */
    public function onTask(SwooleHttpServer $server, int $taskId, int $fromWorkerId, string $data): mixed
    {
        $this->getLogger()->info(
            "Received task #{$taskId} from worker #{$fromWorkerId}",
            ['worker_id' => $server->worker_id, 'task_id' => $taskId],
        );

        try {
            $task = unserialize($data);

            if ($task instanceof Task) {
                return $task->handle();
            }

            throw new \InvalidArgumentException('Data received is not a valid Task object.');
        } catch (Throwable $e) {
            $this->getLogger()->error(
                "Task #{$taskId} failed: " . $e->getMessage(),
                ['exception' => $e, 'worker_id' => $server->worker_id, 'task_id' => $taskId],
            );

            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Handle the completion of a task.
     * This method runs back in the original HTTP Worker process.
     */
    public function onFinish(SwooleHttpServer $server, int $taskId, mixed $data): void
    {
        $logContext = ['task_id' => $taskId, 'worker_id' => $server->worker_id];

        if (is_array($data) && isset($data['error'])) {
            $this->getLogger()->warning(
                "Task #{$taskId} completed with an error: {$data['message']}",
                $logContext,
            );
        } else {
            $this->getLogger()->info("Finished task #{$taskId}", $logContext);
        }
    }

    /**
     * Initialize services when a worker process starts.
     * This is the perfect place to initialize connection pools.
     */
    public function onWorkerStart(SwooleHttpServer $server, int $workerId): void
    {
        // CRITICAL: This must be the first thing to run in the worker.
        // It tells Revolt to use Swoole's event loop, enabling all Amp/Revolt
        // based libraries (like our Fiber pools) to work correctly.
        \Revolt\EventLoop::setDriver(new SwooleDriver());

        try {
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }

            $this->app->bootstrap();

            $this->isDebug = $this->app->make('config')->get('app.debug', false);

            $this->kernel = $this->app->make(\Core\Contracts\Http\Kernel::class);
            $this->exceptionHandler = $this->app->make(ExceptionHandler::class);

            $workerType = $server->taskworker ? 'TaskWorker' : 'Worker';
            $this->getLogger()->info(
                "Swoole {$workerType} started successfully",
                ['worker_id' => $workerId, 'pid' => getmypid()],
            );

            if (!$server->taskworker && $workerId === 0) {
                if ($this->fileWatcher !== null) {
                    $this->getLogger()->info('Delayed job scheduler is disabled because file watcher is active.');
                    return;
                }

                $checkInterval = config('queue.scheduler.check_interval', 1000);
                if ($checkInterval > 0) {
                    $scheduler = $this->app->make(DelayedJobScheduler::class);
                    $this->delayedJobTimerId = \Swoole\Timer::tick($checkInterval, $scheduler);
                    $this->getLogger()->debug('[TRACE] Delayed job scheduler started on worker #0.', ['interval_ms' => $checkInterval]);
                }
            }

            \Swoole\Coroutine::create(function () {
                foreach ($this->app->getTagged(PoolManager::class) as $manager) {
                    $manager->warmup();
                }
                $this->getLogger()->debug('Connection pools warmed up.');
            });
        } catch (Throwable $e) {
            try {
                $this->getLogger()->critical(
                    "Worker #{$workerId} failed to start: " . $e->getMessage(),
                    ['exception' => $e, 'file' => $e->getFile(), 'line' => $e->getLine()],
                );
            } catch (Throwable $logError) {
                error_log('Worker start failure: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
                error_log('Logging service also failed: ' . $logError->getMessage());
            }

            if (isset($this->exceptionHandler)) {
                $this->exceptionHandler->report($e);
            }
            $server->stop($workerId);
        }
    }

    /**
     * Clean up resources when a worker process stops.
     */
    public function onWorkerStop(SwooleHttpServer $server, int $workerId): void
    {
        // Close all managed connection pools gracefully.
        // This iterates through services tagged with `PoolManager`.
        foreach ($this->app->getTagged(PoolManager::class) as $manager) {
            $manager->close();
        }

        if ($this->delayedJobTimerId !== null) {
            \Swoole\Timer::clear($this->delayedJobTimerId);
        }

        $workerType = $server->taskworker ? 'TaskWorker' : 'Worker';
        $this->getLogger()->info("Swoole {$workerType} #{$workerId} stopped gracefully.");
    }

    /**
     * Cleans up application state after a request is handled.
     *
     * @param string $requestId
     */
    protected function cleanupAfterRequest(string $requestId): void
    {
        $this->app->forgetInstance(ServerRequestInterface::class);
        $this->app->forgetInstance(SwooleRequest::class);
        $this->app->forgetInstance('request_id');

        if (++$this->requestCount % 1000 === 0) {
            $this->getLogger()->debug("[TRACE] Request [{$requestId}]: State reset and garbage collection complete.");
            gc_collect_cycles();
        }
    }

    protected function transformRequest(SwooleRequest $swooleRequest): ServerRequestInterface
    {
        return $this->psr7Bridge->toPsr7Request($swooleRequest);
    }

    protected function transformResponse(ResponseInterface $response, SwooleResponse $swooleResponse): void
    {
        // Thêm debug header nếu debug được bật
        if ($this->app->bound(DebugManager::class) && $this->app->make(DebugManager::class)->isEnabled()) {
            $requestId = $this->app->make('request_id');
            $response = $response->withHeader('X-Debug-ID', $requestId);
        }

        $this->psr7Bridge->toSwooleResponse($response, $swooleResponse);
    }

    /**
     * Log an incoming request and its response to the terminal in development.

     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param float $startTime
     */
    protected function logRequest(ServerRequestInterface $request, ResponseInterface $response, float $startTime): void
    {
        if (!$this->isDebug) {
            return;
        }

        $duration = round((microtime(true) - $startTime) * 1000);
        $requestId = $this->app->make('request_id');

        $context = [
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'path' => $request->getUri()->getPath(),
            'query_params' => $request->getQueryParams(),
            'status_code' => $response->getStatusCode(),
            'reason_phrase' => $response->getReasonPhrase(),
            'duration_ms' => $duration,
            'remote_addr' => $request->getServerParams()['REMOTE_ADDR'] ?? '?.?.?.?',
            'request_headers' => $request->getHeaders(),
            'response_headers' => $response->getHeaders(),
        ];

        $requestBody = $request->getParsedBody();
        if ($requestBody) {
            $context['request_body'] = $requestBody;
        } elseif ($request->getBody()->getSize() > 0 && $request->getBody()->getSize() < 1024 * 10) {
            try {
                $request->getBody()->rewind();
                $context['raw_request_body'] = $request->getBody()->getContents();
            } catch (Throwable $e) {
                $context['raw_request_body_error'] = $e->getMessage();
            }
        }

        if ($response->getBody()->getSize() > 0 && $response->getBody()->getSize() < 1024 * 10) {
            try {
                $response->getBody()->rewind();
                $context['response_body'] = $response->getBody()->getContents();
            } catch (Throwable $e) {
                $context['response_body_error'] = $e->getMessage();
            }
        }

        // Ghi log với message và context
        $this->getLogger()->info(
            sprintf(
                'Request [ID: %s] "%s %s" %d %s (%dms)',
                $requestId,
                $request->getMethod(),
                $request->getUri()->getPath(),
                $response->getStatusCode(),
                $response->getReasonPhrase(),
                $duration,
            ),
            $context,
        );
    }

    /**
     * Get the logger instance.
     *
     * @return \Psr\Log\LoggerInterface
     */
    private function getLogger(): LoggerInterface
    {
        return $this->app->make(LoggerInterface::class);
    }
}
