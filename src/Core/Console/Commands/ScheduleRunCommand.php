<?php

namespace Core\Console\Commands;

use App\Console\ScheduleKernel;
use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\Support\Facades\Queue;
use Throwable;

class ScheduleRunCommand extends BaseCommand
{
    protected string $signature = 'schedule:run';
    protected string $description = 'Run the scheduled commands.';
    protected bool $shouldQuit = false;

    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return $this->signature;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function handle(): int
    {
        $this->registerSignalHandler();
        $this->info('Scheduler is running. Press Ctrl+C to stop.');

        /** @var ScheduleKernel $kernel */
        $kernel = $this->app->make(ScheduleKernel::class);

        while (!$this->shouldQuit) {
            try {
                $dueEvents = $kernel->getEvents();

                if (count($dueEvents) > 0) {
                    $this->line(sprintf('<fg=yellow>[%s] Running scheduled jobs...</>', date('Y-m-d H:i:s')));
                }

                foreach ($dueEvents as $event) {
                    $job = $event->getJob();
                    $jobName = get_class($job);

                    $this->info("  - Dispatching job: {$jobName}");
                    Queue::dispatch($job);
                }
            } catch (Throwable $e) {
                $this->app->make(\Core\Contracts\Exceptions\Handler::class)->report($e);
            }

            $this->sleepUntilNextMinute();
        }

        $this->info('Scheduler is shutting down.');
        return self::SUCCESS;
    }

    protected function sleepUntilNextMinute(): void
    {
        $sleepTime = 60 - (time() % 60);
        sleep($sleepTime);
    }

    /**
     * Register signal handlers for graceful shutdown.
     */
    protected function registerSignalHandler(): void
    {
        if (!extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);

        pcntl_signal(SIGINT, fn () => $this->shouldQuit = true); // Ctrl+C
        pcntl_signal(SIGTERM, fn () => $this->shouldQuit = true); // Process manager signal
    }
}
