<?php

namespace Core\Console\Commands;

use App\Console\Kernel as AppConsoleKernel;
use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\Console\Scheduling\Schedule;

class ScheduleRunCommand extends BaseCommand
{
    public function __construct(
        private AppConsoleKernel $kernel
    ) {
        parent::__construct();
    }

    public function signature(): string
    {
        return 'schedule:run';
    }

    public function description(): string
    {
        return 'Run the scheduled commands.';
    }

    public function handle(): int
    {
        $this->comment('Running scheduled tasks...');

        $schedule = new Schedule();

        $reflection = new \ReflectionClass($this->kernel);
        $method = $reflection->getMethod('schedule');
        $method->setAccessible(true);
        $method->invoke($this->kernel, $schedule);

        $dueEvents = array_filter($schedule->getEvents(), fn ($event) => $event->isDue($this->app));

        if (empty($dueEvents)) {
            $this->info('No scheduled commands are due to run.');
            return 0;
        }

        $failures = false;

        foreach ($dueEvents as $event) {
            $description = $event->description ?? get_class($event);
            $this->line("<fg=cyan>Running task:</> {$description}");

            try {
                $event->run($this->app);
            } catch (\Throwable $e) {
                $failures = true;
                $this->error("Task [{$description}] failed: " . $e->getMessage());
            }
        }

        $this->info("\nScheduled task execution finished.");
        return $failures ? 1 : 0;
    }
}