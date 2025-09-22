<?php

namespace Core\Server;

use Core\Application;
use Core\Contracts\Exceptions\Handler as ExceptionHandler;
use Core\Contracts\Task\Task;
use Core\Foundation\StateResetter;
use Core\Queue\DelayedJobScheduler;
use Core\Server\Development\FileWatcher;
use Psr\Log\LoggerInterface;
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
    protected ?ConnectionPoolManager $poolManager = null;
    protected StateResetter $stateResetter;
    protected ExceptionHandler $exceptionHandler;
    protected ?RequestLogger $requestLogger = null;
    protected ?FileWatcher $fileWatcher = null;
    protected bool $isDebug = false;
    /**
     * @var bool
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

        if (isset($_SERVER['argv']) && \in_array('--watch', $_SERVER['argv'], true)) {
            $this->config['watch_files'] = true;
        }

        $this->server = new SwooleHttpServer($host, $port);

        $settings = $this->prepareServerSettings();
        $ignoredKeys = ['host', 'port', 'watch', 'db_pool', 'redis_pool', 'watch_files', 'watch_dir', 'watch_delay', 'watch_recursive', 'pools'];
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

            'watch_files' => false,
            'watch_dir' => array_merge(
                config('server.watch.directories', []),
                [base_path()],
            ),
            'watch_delay' => config('server.watch.delay', 1000),
            'watch_recursive' => true,
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

        if (!empty($settings['watch_files'])) {
            $settings['reload_async'] = true;
            $settings['worker_num'] = 1;
        }

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
        $this->server->on('managerStart', [$this, 'onManagerStart']);
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
    }

    /**
     * Called when the manager process starts. This is the ideal place to start custom processes.
     */
    public function onManagerStart(SwooleHttpServer $server): void
    {
        $this->getLogger()->info('Swoole Manager process has started.');
    }

    /**
     * Called in the master process before the server shuts down.
     */
    public function onShutdown(SwooleHttpServer $server): void
    {
        $this->getLogger()->info('Swoole HTTP server is shutting down gracefully.');
    }

    public function onWorkerExit(SwooleHttpServer $server, int $workerId): void
    {
        $this->getLogger()->warning("Swoole Worker #{$workerId} exited unexpectedly.", ['worker_id' => $workerId]);
    }

    public function handleRequest(SwooleRequest $swooleRequest, SwooleResponse $swooleResponse): void
    {
        (new RequestLifecycle(
            $this->app,
            $this->kernel,
            $this->exceptionHandler,
            $this->stateResetter,
            $this->psr7Bridge,
            $this->isDebug,
        ))->handle($swooleRequest, $swooleResponse);
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
        if (!$this->app->bound('log.task_writer')) {
            $this->app->singleton('log.task_writer', function (Application $app) use ($server) {
                $logManager = new \Core\Logging\LogManager($app);
                $config = $app->make('config')->get('logging.channels.task_worker', $app->make('config')->get('logging.channels.daily'));

                $logger = $logManager->createDailyDriver($config);

                if (class_exists(\Core\Logging\Processor\TaskWorkerContextProcessor::class)) {
                    $processor = new \Core\Logging\Processor\TaskWorkerContextProcessor($server);
                    $logger->pushProcessor($processor);
                }

                return $logger;
            });
        }

        $this->getLogger()->info(
            "Received task #{$taskId} from worker #{$fromWorkerId}",
            ['worker_id' => $server->worker_id, 'task_id' => $taskId],
        );

        try {
            $task = unserialize($data, [
                'allowed_classes' => fn (string $class) => is_a($class, Task::class, true),
            ]);

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
        $this->app->instance('swoole.server', $server);

        try {
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }

            $this->app->bootstrap();

            $this->isDebug = $this->app->make('config')->get('app.debug', false);

            $this->kernel = $this->app->make(\Core\Contracts\Http\Kernel::class);
            $this->exceptionHandler = $this->app->make(ExceptionHandler::class);
            $this->stateResetter = $this->app->make(StateResetter::class);
            $this->poolManager = new ConnectionPoolManager(
                $this->app,
                $this->getLogger(),
                $this->config,
            );
            $this->requestLogger = new RequestLogger(
                $this->app,
                $this->getLogger(),
                $this->isDebug,
            );

            $workerType = $server->taskworker ? 'TaskWorker' : 'Worker';
            $this->getLogger()->info(
                "Swoole {$workerType} started successfully",
                ['worker_id' => $workerId, 'pid' => getmypid()],
            );

            $this->poolManager->initializePools($server->taskworker);

            if (!$server->taskworker && $workerId === 0) {
                if ($this->fileWatcher !== null) {
                    $this->getLogger()->info('Delayed job scheduler is disabled because file watcher is active.');
                    return;
                }

                $checkInterval = config('queue.scheduler.check_interval', 1000);
                if ($checkInterval > 0) {
                    $scheduler = $this->app->make(DelayedJobScheduler::class);
                    $this->delayedJobTimerId = \Swoole\Timer::tick($checkInterval, function () use ($scheduler) {
                        \Swoole\Coroutine::create($scheduler);
                    });
                    $this->getLogger()->debug('[TRACE] Delayed job scheduler started on worker #0.', ['interval_ms' => $checkInterval]);
                }
            }

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

            // Stop the worker immediately to prevent it from handling requests in a broken state.
            $server->stop($workerId);
        }
    }

    /**
     * Clean up resources when a worker process stops.
     */
    public function onWorkerStop(SwooleHttpServer $server, int $workerId): void
    {
        $this->poolManager?->closePools();

        if ($this->delayedJobTimerId !== null) {
            \Swoole\Timer::clear($this->delayedJobTimerId);
        }

        $workerType = $server->taskworker ? 'TaskWorker' : 'Worker';
        $this->getLogger()->info("Swoole {$workerType} #{$workerId} stopped gracefully.");
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
