<?php

namespace Core\Console\Commands\Queue;

use Core\Console\Contracts\BaseCommand;
use Core\Queue\QueueWorker;
use Throwable;

class WorkCommand extends BaseCommand
{
    protected string $signature = 'queue:work
        {connection? : The name of the queue connection to work}
        {--queue= : The queue(s) to listen on, comma-separated}
        {--once : Only process the next job on the queue}
        {--tries=1 : Number of times to attempt a job before logging it failed}
        {--timeout=60 : The number of seconds a child process can run}
        {--sleep=3 : Number of seconds to sleep when no job is available}
        {--memory=128 : The memory limit in megabytes}';
    protected string $description = 'Start processing jobs on the queue as a daemon.';

    /**
     * Cờ để cho biết worker nên thoát khỏi vòng lặp.
     * @var bool
     */
    protected bool $shouldQuit = false;

    public function __construct(protected QueueWorker $worker)
    {
        parent::__construct();
    }

    public function signature(): string
    {
        return $this->signature;
    }

    public function description(): string
    {
        return $this->description;
    }

    /**
     * Thực thi lệnh console.
     */
    public function handle(): int
    {
        $connectionName = $this->argument('connection')
                        ?: $this->app->make('config')->get('queue.default');

        $queue = $this->getQueue($connectionName);

        $this->listenForEvents();
        $this->registerSignalHandler();

        $this->info("Starting worker for connection '{$connectionName}' on queue '{$queue}'. Press Ctrl+C to stop.");

        while (true) {
            // Kiểm tra xem có tín hiệu yêu cầu dừng worker không.
            if ($this->shouldQuit) {
                $this->info('Worker is shutting down...');
                return self::SUCCESS;
            }

            // Kiểm tra giới hạn bộ nhớ để tránh memory leak.
            if ($this->memoryExceeded((int)$this->option('memory'))) {
                $this->error('Worker has exceeded memory limit. Shutting down.');
                return self::FAILURE;
            }

            // Yêu cầu worker xử lý job tiếp theo.
            try {
                $this->worker->runNextJob($connectionName, $queue);
            } catch (Throwable $e) {
                $this->app->make(\Core\Contracts\Exceptions\Handler::class)->report($e);
            }

            // Nếu chỉ chạy một lần, thoát khỏi vòng lặp.
            if ($this->option('once')) {
                break;
            }

            // Tạm dừng một chút để tránh chiếm dụng CPU khi không có job.
            sleep((int)$this->option('sleep'));
        }

        return self::SUCCESS;
    }

    /**
     * Lấy tên queue từ option hoặc config.
     */
    protected function getQueue(string $connection): string
    {
        return $this->option('queue')
            ?: $this->app->make('config')->get("queue.connections.{$connection}.queue", 'default');
    }

    /**
     * Lắng nghe các sự kiện của queue để hiển thị log.
     */
    protected function listenForEvents(): void
    {
        $events = $this->app->make('events');

        $resolveName = fn ($job) => method_exists($job, 'resolveName') ? $job->resolveName() : get_class($job);

        $events->listen(\Core\Events\Queue\JobProcessing::class, function ($event) use ($resolveName) {
            $this->line(sprintf('<fg=yellow>[%s] Processing:</> %s', date('Y-m-d H:i:s'), $resolveName($event->job)));
        });

        $events->listen(\Core\Events\Queue\JobProcessed::class, function ($event) use ($resolveName) {
            $this->line(sprintf('<fg=green>[%s] Processed:</> %s', date('Y-m-d H:i:s'), $resolveName($event->job)));
        });

        $events->listen(\Core\Events\Queue\JobFailed::class, function ($event) use ($resolveName) {
            $this->error(sprintf('[%s] Failed: %s', date('Y-m-d H:i:s'), $resolveName($event->job)));
            $this->error($event->exception->getMessage());
        });
    }

    /**
     * Đăng ký xử lý tín hiệu để dừng worker một cách an toàn.
     */
    protected function registerSignalHandler(): void
    {
        if (!extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);

        pcntl_signal(SIGINT, fn () => $this->shouldQuit = true); // Ctrl+C
        pcntl_signal(SIGTERM, fn () => $this->shouldQuit = true); // Tín hiệu từ process manager
    }

    /**
     * Kiểm tra xem worker có vượt quá giới hạn bộ nhớ không.
     */
    protected function memoryExceeded(int $memoryLimit): bool
    {
        return (memory_get_usage(true) / 1024 / 1024) >= $memoryLimit;
    }
}
