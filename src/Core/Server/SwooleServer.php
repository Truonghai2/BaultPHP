<?php

namespace Core\Server;

use Core\Application;
use Core\Contracts\StatefulService;
use Core\Contracts\Task\Task;
use Core\Database\Swoole\SwoolePdoPool;
use Http\JsonResponse;
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
    protected bool $hotReloadEnabled = false;
    protected ?int $delayedJobTimerId = null;
    protected SwoolePsr7Bridge $psr7Bridge;

    public function __construct(Application $app)
    {
        $this->app = $app;

        // Lấy cấu hình do người dùng định nghĩa từ file config.
        $this->config = config('server.swoole', []);

        $host = $this->config['host'] ?? '127.0.0.1';
        $port = $this->config['port'] ?? 9501;

        $this->server = new SwooleHttpServer($host, $port);

        // Chuẩn bị và lọc các settings để chỉ truyền các tùy chọn hợp lệ cho Swoole.
        // Các tùy chọn như 'host', 'port', 'watch', 'db_pool' là của framework, không phải của Swoole.
        $settings = $this->prepareServerSettings();
        $ignoredKeys = ['host', 'port', 'watch', 'db_pool', 'watch_file'];
        $validSwooleSettings = array_filter(
            $settings,
            fn($key) => !in_array($key, $ignoredKeys, true),
            ARRAY_FILTER_USE_KEY
        );
        $this->server->set($validSwooleSettings);
        $this->psr7Bridge = new SwoolePsr7Bridge();
    }
    /**
     * Cung cấp các cấu hình mặc định, được tối ưu cho hiệu năng và độ ổn định.
     * Người dùng có thể ghi đè các giá trị này trong config/server.php.
     *
     * @return array
     */
    private function getDefaultSettings(): array
    {
        return [
            // --- Cấu hình Worker ---
            'worker_num' => swoole_cpu_num(),
            'task_worker_num' => swoole_cpu_num(),
            'task_enable_coroutine' => true,

            // --- Cấu hình cho Production ---
            // Mặc định là false, người dùng nên bật qua .env cho môi trường production.
            'daemonize' => false,
            'pid_file' => storage_path('logs/swoole.pid'),
            'log_file' => storage_path('logs/swoole.log'),
            // Mặc định log ở mức INFO, có thể đổi thành WARNING trong production.
            'log_level' => SWOOLE_LOG_INFO,

            // --- Tối ưu hóa hiệu năng & độ ổn định ---
            'max_request' => 50000,
            'task_max_request' => 50000,
            'max_connection' => 10000,
            'open_tcp_nodelay' => true,

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

        // Xử lý logic đặc biệt cho hot-reload.
        if (!empty($settings['watch']['directories']) && is_array($settings['watch']['directories'])) {
            $this->hotReloadEnabled = true;
            $settings['reload_async'] = true;
            // Sử dụng watch setting của Swoole để theo dõi các thư mục.
            $settings['watch_file'] = true; // Cần thiết cho một số phiên bản Swoole
        } else {
            // Đảm bảo rằng watch bị vô hiệu hóa nếu không được cấu hình đúng.
            unset($settings['watch']);
        }

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
     * Đăng ký tất cả các callback sự kiện cho Swoole server.
     */
    protected function registerServerEvents(): void
    {
        $this->server->on('start', [$this, 'onStart']);
        $this->server->on('workerStart', [$this, 'onWorkerStart']);
        $this->server->on('workerStop', [$this, 'onWorkerStop']);
        $this->server->on('request', [$this, 'handleRequest']);
        $this->server->on('task', [$this, 'onTask']);
        $this->server->on('finish', [$this, 'onFinish']);
    }

    public function onStart(SwooleHttpServer $server): void
    {
        $this->getLogger()->info('Swoole HTTP server is started', ['url' => "http://{$server->host}:{$server->port}"]);
        if ($this->hotReloadEnabled) {
            $this->getLogger()->info('Hot-reload is enabled. Watching for file changes...', ['directories' => $this->config['watch']['directories']]);
        }
    }

    public function handleRequest(SwooleRequest $swooleRequest, SwooleResponse $swooleResponse): void
    {
        // The "Sandbox" Pattern: Execute request handling in a try-finally block
        // to ensure state is always cleaned up, preventing memory leaks between requests.
        try {
            try {
                // Chuyển đổi Swoole Request thành PSR-7 Request
                $request = $this->transformRequest($swooleRequest);

                // Xử lý request thông qua Kernel của framework
                $response = $this->app->handle($request);
            } catch (Throwable $e) {
                // Nếu có lỗi xảy ra trong quá trình xử lý request,
                // chúng ta sẽ bắt lại, ghi log và tạo một response lỗi 500.
                // Điều này đảm bảo server không bị sập và client nhận được phản hồi.
                $this->getLogger()->error(
                    'Swoole Request Handling Error: ' . $e->getMessage(),
                    ['exception' => $e]
                );
                $response = new JsonResponse(['message' => 'Internal Server Error'], 500);
            }

            // Chuyển đổi PSR-7 Response thành Swoole Response
            $this->transformResponse($response, $swooleResponse);
        } finally {
            // Dọn dẹp tất cả các service đã được tag là "stateful".
            // Việc này cực kỳ quan trọng để tránh rò rỉ bộ nhớ.
            foreach ($this->app->getTagged(StatefulService::class) as $service) {
                $service->resetState();
            }

            // Buộc PHP dọn dẹp các tham chiếu vòng tròn để giải phóng bộ nhớ ngay lập tức.
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
        // Swoole's task system requires the data to be a string. We serialize the task object.
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
            ['worker_id' => $server->worker_id, 'task_id' => $taskId]
        );

        try {
            // Unserialize the task object.
            $task = unserialize($data);

            if (!$task instanceof Task) {
                throw new \InvalidArgumentException('Data received is not a valid Task object.');
            }

            // Execute the task's logic and return the result.
            // The result will be sent to the onFinish callback in the original worker.
            return $task->handle();
        } catch (Throwable $e) {
            $this->getLogger()->error(
                "Task #{$taskId} failed: " . $e->getMessage(),
                ['exception' => $e, 'worker_id' => $server->worker_id, 'task_id' => $taskId]
            );

            // Return an error indicator to onFinish.
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
                $logContext
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
        }

        // Rất quan trọng: Xóa cache của OPCache khi worker khởi động.
        // Điều này đảm bảo rằng khi "graceful reload", các worker mới sẽ load code mới nhất.
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        $workerType = $server->taskworker ? 'TaskWorker' : 'Worker';
        $this->getLogger()->info(
            "Swoole {$workerType} started",
            ['worker_id' => $workerId, 'pid' => getmypid()]
        );

        $dbPoolConfig = $this->config['db_pool'] ?? [];
        
        if ($dbPoolConfig['enabled']) {
            $dbConnectionName = $dbPoolConfig['connection'];
            $dbConfig = config("database.connections.{$dbConnectionName}");

            $poolSize = $server->taskworker
                ? ($dbPoolConfig['task_worker_pool_size'] ?? 10)
                : ($dbPoolConfig['worker_pool_size'] ?? 10);

            SwoolePdoPool::init($dbConfig, $poolSize);
        }

        // Chỉ khởi động timer cho delayed jobs trên worker đầu tiên để tránh trùng lặp.
        // Và chỉ khởi động cho HTTP worker, không phải Task worker.
        if (!$server->taskworker && $workerId === 0) {
            $this->startDelayedJobScheduler();
        }
    }

    /**
     * Clean up resources when a worker process stops.
     */
    public function onWorkerStop(SwooleHttpServer $server, int $workerId): void
    {
        SwoolePdoPool::close();

        // Dọn dẹp timer khi worker dừng lại.
        if ($this->delayedJobTimerId !== null) {
            \Swoole\Timer::clear($this->delayedJobTimerId);
        }

        $this->getLogger()->info("Swoole Worker #{$workerId} stopped.");
    }

    protected function transformRequest(SwooleRequest $swooleRequest): \Psr\Http\Message\ServerRequestInterface
    {
        return $this->psr7Bridge->toPsr7Request($swooleRequest);
    }

    protected function transformResponse(\Psr\Http\Message\ResponseInterface $response, SwooleResponse $swooleResponse): void
    {
        $this->psr7Bridge->toSwooleResponse($response, $swooleResponse);
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

    /**
     * Starts a periodic timer to check for and dispatch delayed jobs from Redis.
     */
    protected function startDelayedJobScheduler(): void
    {
        $checkInterval = 1000; // Kiểm tra mỗi 1000ms (1 giây)
        $queueName = 'queues:delayed:default'; // Tạm thời hardcode, có thể lấy từ config

        $this->delayedJobTimerId = \Swoole\Timer::tick($checkInterval, function () use ($queueName) {
            try {
                /** @var \Predis\ClientInterface|\Redis $redis */
                $redis = $this->app->make('redis');

                // Lấy tất cả các job có timestamp <= thời gian hiện tại
                $jobsToProcess = $redis->zrangebyscore($queueName, '-inf', time());

                if (empty($jobsToProcess)) {
                    return;
                }

                // Xóa các job đã lấy ra khỏi hàng đợi để tránh xử lý lại
                $redis->zremrangebyscore($queueName, '-inf', time());

                $this->getLogger()->info(sprintf('Found %d delayed jobs to process.', count($jobsToProcess)));

                foreach ($jobsToProcess as $serializedJob) {
                    $job = unserialize($serializedJob);
                    if ($job instanceof \Core\Contracts\Queue\Job) {
                        // Dispatch job vào task worker để thực thi
                        $this->dispatchTask(new \Core\Queue\Jobs\ProcessJobTask($job));
                    }
                }
            } catch (Throwable $e) {
                $this->getLogger()->error('Delayed job scheduler error: ' . $e->getMessage(), ['exception' => $e]);
            }
        });

        $this->getLogger()->info('Delayed job scheduler started on worker #0.');
    }
}
