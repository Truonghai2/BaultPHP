<?php

namespace Core\Server;

use Core\Application;
use Core\Contracts\Exceptions\Handler as ExceptionHandler;
use Core\Contracts\Task\Task;
use Core\Foundation\StateResetter;
use Core\Logging\LogManager;
use Core\Queue\DelayedJobScheduler;
use Core\Server\Development\FileWatcher;
use Psr\Log\LoggerInterface;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Http\Server as SwooleHttpServer;
use Swoole\Process;
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
     * Counter for the number of requests processed by the worker.
     * Used to trigger periodic cleanup tasks.
     *
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
        $ignoredKeys = ['host', 'port', 'watch', 'db_pool', 'redis_pool', 'watch_files', 'watch_dir', 'watch_delay', 'watch_recursive', 'pools', 'guzzle'];
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

        $this->registerCustomProcesses();
    }

    /**
     * Provides default configurations, optimized for performance and stability.
     * Users can override these values in config/server.php.
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
     * Prepares the final settings array to be passed to the Swoole server.
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
     * Registers user-defined custom processes.
     * Must be called before server->start().
     */
    protected function registerCustomProcesses(): void
    {
        if (!$this->app->hasBeenBootstrapped()) {
            $this->app->bootstrap();
        }

        $processesToRegister = $this->config['processes'] ?? [];

        foreach ($processesToRegister as $processClass) {
            if (class_exists($processClass)) {
                $processInstance = new $processClass($this->app, $this->server, $this->getLogger());
                $process = new Process($processInstance, false, 2, true); // redirect_stdin_stdout, pipe_type, enable_coroutine
                $this->server->addProcess($process);
                $this->getLogger()->debug("Registered custom process: {$processClass}");
            } else {
                $this->getLogger()->warning("Custom process class not found: {$processClass}");
            }
        }
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
     * Registers all event callbacks for the Swoole server.
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
        $this->server->on('pipeMessage', [$this, 'onPipeMessage']);
    }

    public function onStart(SwooleHttpServer $server): void
    {
        $this->getLogger()->info('Swoole HTTP server is started', ['url' => "http://{$server->host}:{$server->port}"]);

        try {
            $this->app->bootstrap();

            $this->isDebug = $this->app->make('config')->get('app.debug', false);

            // Pool manager will be initialized in each worker
        } catch (Throwable $e) {
            $this->getLogger()->critical('Failed to bootstrap application in master process: ' . $e->getMessage(), ['exception' => $e]);
            $server->shutdown();
        }
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
     * Sends a message from a worker process to the master process.
     * The master process can then decide what to do with it.
     *
     * @param string $message Serialized data.
     * @return bool
     */
    public function sendMessageToMaster(string $message): bool
    {
        return $this->server->worker_id !== -1 && $this->server->sendMessage($message, -1);
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
        $this->getLogger()->info("Received task #{$taskId} from worker #{$fromWorkerId}", ['task_id' => $taskId]);

        try {
            $task = unserialize($data, [
                'allowed_classes' => fn (string $class) => is_a($class, Task::class, true),
            ]);

            if ($task instanceof Task) {
                return $this->app->call([$task, 'handle'], ['logger' => $this->app->make('log.task')]);
            }

            throw new \InvalidArgumentException('Data received is not a valid Task object.');
        } catch (Throwable $e) {
            $this->getLogger()->error(
                "Task #{$taskId} failed in onTask: " . $e->getMessage(),
                ['exception' => $e, 'task_id' => $taskId],
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
        $workerType = $server->taskworker ? 'TaskWorker' : 'Worker';

        if ($server->taskworker) {
            $this->app->singleton('log.task', function (Application $app) use ($server) {
                /** @var \Core\Logging\LogManager $logManager */
                $logManager = new LogManager($app);
                $logger = $logManager->channel('task_worker');

                if (class_exists(\Core\Logging\Processor\TaskWorkerContextProcessor::class)) {
                    $processor = new \Core\Logging\Processor\TaskWorkerContextProcessor($server);
                    if ($logger instanceof LogManager) {
                        $logger->pushProcessor($processor);
                    }
                }
                return $logger;
            });
            $this->app->instance(LoggerInterface::class, $this->app->make('log.task'));
        }

        try {
            if (function_exists('opcache_reset') && !$this->isProduction()) {
                opcache_reset();
            }

            $this->kernel = $this->app->make(\Core\Contracts\Http\Kernel::class);
            $this->exceptionHandler = $this->app->make(ExceptionHandler::class);
            $this->stateResetter = $this->app->make(StateResetter::class);

            $this->poolManager = new ConnectionPoolManager(
                $this->app,
                $this->getLogger(),
                $this->config,
            );
            $this->poolManager->initializePools($server->taskworker);

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

            $server->stop($workerId);
        }
    }

    /**
     * Handles messages sent from custom processes.
     *
     * @param SwooleHttpServer $server
     * @param int $fromWorkerId ID of the process that sent the message.
     * @param string $message The data sent.
     */
    public function onPipeMessage(SwooleHttpServer $server, int $fromWorkerId, string $message): void
    {
        if (isset($server->worker_id)) {
            $this->handleMessageInWorker($message, $server->worker_id);
            return;
        }

        $data = @unserialize($message);
        if (is_array($data) && isset($data['type'])) {
            for ($i = 0; $i < $server->setting['worker_num']; $i++) {
                $server->sendMessage($message, $i);
            }
        }
    }

    /**
     * Logic to execute when a worker receives a message.
     */
    private function handleMessageInWorker(string $message, int $workerId): void
    {
        $data = @unserialize($message);
        if (is_array($data) && isset($data['type'])) {
            $this->getLogger()->info("Worker #{$workerId} received a message of type '{$data['type']}'", ['payload' => $data['payload'] ?? null]);
        }
    }

    /**
     * Clean up resources when a worker process stops.
     */
    public function onWorkerStop(SwooleHttpServer $server, int $workerId): void
    {
        if ($this->delayedJobTimerId !== null) {
            \Swoole\Timer::clear($this->delayedJobTimerId);
        }

        $workerType = $server->taskworker ? 'TaskWorker' : 'Worker';
        $this->getLogger()->info("Swoole {$workerType} #{$workerId} stopped gracefully.");
    }

    /**
     * Checks if the server is running in a production environment.
     */
    private function isProduction(): bool
    {
        if ($this->isDebug === null) {
            $this->isDebug = $this->app->make('config')->get('app.debug', false);
        }
        return !$this->isDebug;
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
