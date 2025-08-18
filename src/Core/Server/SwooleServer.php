<?php

namespace Core\Server;

use Core\Application;
use Core\Contracts\Exceptions\Handler as ExceptionHandler;
use Core\Contracts\StatefulService;
use Core\Contracts\Task\Task;
use Core\Exceptions\ServiceUnavailableException;
use Core\Queue\DelayedJobScheduler;
use Core\Server\Development\FileWatcher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Http\Server as SwooleHttpServer;
use Throwable;

class SwooleServer
{
    protected Application $app;
    protected SwooleHttpServer $server;
    protected array $config;
    protected ?int $delayedJobTimerId = null;
    protected SwoolePsr7Bridge $psr7Bridge;
    protected \Core\Contracts\Http\Kernel $kernel;
    protected ExceptionHandler $exceptionHandler;
    protected ?ConnectionPoolManager $poolManager = null;
    protected ?FileWatcher $fileWatcher = null;
    protected bool $isDebug = false;

    public function __construct(Application $app)
    {
        // Không thể log ở đây vì logger chưa được bootstrap.
        $this->app = $app;

        // Lấy cấu hình do người dùng định nghĩa từ file config.
        $this->config = config('server.swoole', []);

        // Trong môi trường container (như Docker), server phải lắng nghe trên '0.0.0.0'
        // để có thể nhận kết nối từ các container khác (ví dụ: Nginx reverse proxy).
        // Lắng nghe trên '127.0.0.1' sẽ chỉ chấp nhận kết nối từ bên trong chính container đó.
        $host = $this->config['host'] ?? '0.0.0.0';
        $port = $this->config['port'] ?? 9501;

        $this->server = new SwooleHttpServer($host, $port);

        // Chuẩn bị và lọc các settings để chỉ truyền các tùy chọn hợp lệ cho Swoole.
        // Các tùy chọn như 'host', 'port', 'watch', 'db_pool' là của framework, không phải của Swoole.
        $settings = $this->prepareServerSettings();
        $ignoredKeys = ['host', 'port', 'watch', 'db_pool', 'redis_pool', 'watch_file'];
        $validSwooleSettings = array_filter(
            $settings,
            fn ($key) => !in_array($key, $ignoredKeys, true),
            ARRAY_FILTER_USE_KEY,
        );
        $this->server->set($validSwooleSettings);
        $this->psr7Bridge = new SwoolePsr7Bridge();

        // Initialize helper managers based on configuration
        $this->poolManager = new ConnectionPoolManager($this->app, $this->getLogger(), $this->config);

        // Initialize the file watcher only in development environments.
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
            // --- Cấu hình Worker ---
            // Mặc định: 1x số lõi CPU cho dev, 2x cho prod (tối ưu cho I/O-bound).
            // Có thể ghi đè qua config/server.php và .env.
            'worker_num' => swoole_cpu_num() * ($isProduction ? 2 : 1),
            'task_worker_num' => swoole_cpu_num(), // Giữ mặc định, có thể tùy chỉnh.
            'task_enable_coroutine' => true,

            // --- Cấu hình cho Production ---
            'pid_file' => storage_path('logs/swoole.pid'),
            'log_file' => storage_path('logs/swoole.log'),
            // Mặc định: INFO cho dev, WARNING cho prod để giảm I/O.
            'log_level' => $isProduction ? SWOOLE_LOG_WARNING : SWOOLE_LOG_INFO,

            // --- Tối ưu hóa hiệu năng & độ ổn định ---
            // Khởi động lại worker sau một số lượng request nhất định để giải phóng bộ nhớ.
            // 10000 là một con số an toàn cho production.
            'max_request' => 10000,
            // Thời gian chờ tối đa (giây) cho các worker hoàn thành request đang xử lý
            // trước khi bị buộc dừng trong quá trình graceful shutdown.
            // Đây là chìa khóa cho việc tắt server an toàn.
            'max_wait_time' => 30,
            'task_max_request' => 10000,
            'max_connection' => 10000,

            // Bật các giao thức TCP để cải thiện hiệu năng mạng.
            'open_tcp_nodelay' => true,

            // Bật HTTP/2 nếu proxy hỗ trợ.
            'open_http2_protocol' => true,

            // Kích thước bộ đệm đầu ra. Tăng giá trị này giúp cải thiện hiệu năng
            // khi gửi các response lớn. (32MB)
            'buffer_output_size' => 32 * 1024 * 1024,

            // Tối ưu hóa việc upload file lớn.
            'http_parse_post' => true,
            'upload_tmp_dir' => storage_path('app/uploads'),

            // Mặc định không bật hot-reload
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
        // Bắt đầu với cấu hình mặc định, an toàn.
        $settings = $this->getDefaultSettings();

        // Ghi đè bằng cấu hình của người dùng từ config/server.php.
        // array_replace_recursive tốt hơn array_merge cho các mảng config đa cấp.
        $settings = array_replace_recursive($settings, $this->config);

        // Luôn bật hot-reload nếu môi trường là 'local' và có cấu hình 'watch'.
        // Điều này đảm bảo `serve:start` cũng có hot-reload khi phát triển.
        // Sử dụng config('app.env') thay vì env() trực tiếp để tận dụng caching config.
        // Hàm config() sẽ fallback về env() nếu giá trị chưa được cache.
        $currentEnv = config('app.env', 'production');
        $isDevelopmentEnv = in_array($currentEnv, ['local', 'development'], true);
        $watchConfigExists = !empty($settings['watch']['directories']) && is_array($settings['watch']['directories']);

        // Khi chạy trong container hoặc thông qua `php cli serve`,
        // server phải luôn chạy ở foreground. Docker/process manager sẽ quản lý việc chạy nền.
        // Ghi đè bất kỳ cấu hình `daemonize` nào từ người dùng để đảm bảo hành vi đúng.
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
        // Start the file watcher if it's enabled.
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
        // This event is triggered when a worker process exits unexpectedly.
        $this->getLogger()->warning("Swoole Worker #{$workerId} exited unexpectedly.", ['worker_id' => $workerId]);
    }

    public function handleRequest(SwooleRequest $swooleRequest, SwooleResponse $swooleResponse): void
    {
        // The "Sandbox" Pattern: Execute request handling in a try-finally block
        // to ensure state is always cleaned up, preventing memory leaks between requests.
        $startTime = microtime(true);
        $requestId = bin2hex(random_bytes(4)); // Tạo ID duy nhất cho request để dễ trace
        try {
            // Bind the request ID to the container so it can be accessed by services like the logger.
            $this->app->instance('request_id', $requestId);

            if ($this->isDebug) {
                $this->getLogger()->debug("Request [{$requestId}] received.", ['method' => $swooleRequest->getMethod(), 'uri' => $swooleRequest->server['request_uri']]);
            }

            // CẢI TIẾN: Chuyển đổi request bên trong try-catch để bắt lỗi parsing.
            $request = $this->transformRequest($swooleRequest);

            // Bind the current PSR-7 request instance to the container.
            // This allows any service resolved during this request's lifecycle
            // to receive the correct request object via dependency injection.
            // This is crucial for long-running applications like Swoole.
            $this->app->instance(ServerRequestInterface::class, $request);

            try {
                $response = $this->kernel->handle($request);
            } catch (ServiceUnavailableException $e) {
                // Handle the specific case where a service is down (circuit is open)
                $response = $this->exceptionHandler->render($request, $e);
                $this->getLogger()->warning("Request [{$requestId}]: Service unavailable, circuit breaker is likely open.", ['exception' => $e->getMessage()]);
            } catch (Throwable $e) {
                $this->exceptionHandler->report($e);
                $response = $this->exceptionHandler->render($request, $e);
                $this->getLogger()->error("Request [{$requestId}]: Unhandled exception caught.", ['exception' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            }

            $this->logRequest($request, $response, $startTime);

            $this->transformResponse($response, $swooleResponse);
        } finally {
            foreach ($this->app->getTagged(StatefulService::class) as $service) {
                $service->resetState();
            }

            // Unbind the request-specific instances to prevent memory leaks.
            $this->app->forgetInstance(ServerRequestInterface::class);
            $this->app->forgetInstance('request_id');

            // In long-running applications, it's a good practice to explicitly
            // trigger the garbage collector to clean up any circular references
            // that might have been created during the request.
            if ($this->isDebug) {
                $this->getLogger()->debug("[TRACE] Request [{$requestId}]: State reset and garbage collection complete.");
            }
            gc_collect_cycles();
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
            // Unserialize the task object.
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

        // You can process the result from the task worker here.
        // For example, log the result or notify a user via WebSocket.
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
        // Logic này cần chạy cho cả HTTP worker và Task worker.
        // Chúng ta cần đảm bảo mỗi worker process (kể cả task worker) có môi trường ứng dụng riêng.

        // Điều này rất quan trọng để các Job có thể sử dụng DI container, DB, etc.
        if (!$this->app->hasBeenBootstrapped()) {
            $this->app->bootstrap();

            // CẢI TIẾN: Cache lại trạng thái debug một lần cho mỗi worker để tối ưu hiệu năng.
            $this->isDebug = $this->app->make('config')->get('app.debug', false);

            $workerType = $server->taskworker ? 'TaskWorker' : 'Worker';
            // Resolve và cache các service cốt lõi một lần cho mỗi worker.
            $this->kernel = $this->app->make(\Core\Contracts\Http\Kernel::class);
            $this->exceptionHandler = $this->app->make(ExceptionHandler::class);
            $this->getLogger()->info("Application bootstrapped for {$workerType} #{$workerId}.");
        }

        // Rất quan trọng: Xóa cache của OPCache khi worker khởi động.
        // Điều này đảm bảo rằng khi "graceful reload", các worker mới sẽ load code mới nhất.
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        $workerType = $server->taskworker ? 'TaskWorker' : 'Worker';
        $this->getLogger()->info(
            "Swoole {$workerType} started",
            ['worker_id' => $workerId, 'pid' => getmypid()],
        );

        // TỐI ƯU HÓA: Khởi tạo các connection pool trong một coroutine riêng.
        // Điều này cho phép onWorkerStart kết thúc ngay lập tức, giúp server khởi động nhanh hơn.
        if ($this->isDebug) {
            $this->getLogger()->debug("[TRACE] {$workerType} #{$workerId}: Initializing connection pools...");
        }
        $this->poolManager?->initializePools($server->taskworker);

        // Chỉ khởi động timer cho delayed jobs trên worker đầu tiên để tránh trùng lặp.
        // Và chỉ cho HTTP worker, không phải Task worker.
        // QUAN TRỌNG: Vô hiệu hóa scheduler khi file watcher đang chạy để tránh vòng lặp hot-reload.
        if (!$server->taskworker && $workerId === 0 && $this->fileWatcher === null) {
            $checkInterval = config('queue.scheduler.check_interval', 1000);
            $scheduler = $this->app->make(DelayedJobScheduler::class);

            $this->delayedJobTimerId = \Swoole\Timer::tick($checkInterval, $scheduler);
            if ($this->isDebug) {
                $this->getLogger()->info('[TRACE] Delayed job scheduler started on worker #0.', ['interval_ms' => $checkInterval]);
            }
        } elseif ($this->fileWatcher !== null) {
            $this->getLogger()->info('Delayed job scheduler is disabled because file watcher is active.');
        }
    }

    /**
     * Clean up resources when a worker process stops.
     */
    public function onWorkerStop(SwooleHttpServer $server, int $workerId): void
    {
        // Delegate pool closing to the manager.
        $this->poolManager?->closePools();

        // Dọn dẹp timer khi worker dừng lại.
        if ($this->delayedJobTimerId !== null) {
            \Swoole\Timer::clear($this->delayedJobTimerId);
        }

        $workerType = $server->taskworker ? 'TaskWorker' : 'Worker';
        $this->getLogger()->info("Swoole {$workerType} #{$workerId} stopped gracefully.");
    }

    protected function transformRequest(SwooleRequest $swooleRequest): ServerRequestInterface
    {
        return $this->psr7Bridge->toPsr7Request($swooleRequest);
    }

    protected function transformResponse(ResponseInterface $response, SwooleResponse $swooleResponse): void
    {
        $this->psr7Bridge->toSwooleResponse($response, $swooleResponse);
    }

    /**
     * Log an incoming request and its response to the terminal in development.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param float $startTime
     */
    protected function logRequest(ServerRequestInterface $request, ResponseInterface $response, float $startTime): void
    {
        // Chỉ log request trong môi trường local để tránh ảnh hưởng hiệu năng ở production.
        // Sử dụng config() được ưu tiên hơn env() trực tiếp, vì nó cho phép ứng dụng
        // hưởng lợi từ việc cache cấu hình.
        if (!$this->isDebug) {
            return;
        }

        $duration = round((microtime(true) - $startTime) * 1000);
        $message = sprintf(
            '[%s] "%s %s" %d %s (%dms)',
            $request->getServerParams()['REMOTE_ADDR'] ?? '?.?.?.?',
            $request->getMethod(),
            $request->getUri()->getPath(),
            $response->getStatusCode(),
            $response->getReasonPhrase(),
            $duration,
        );

        $this->getLogger()->info($message);
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
