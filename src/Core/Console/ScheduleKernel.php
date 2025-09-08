<?php

namespace Core\Console;

use Core\Console\Scheduling\Scheduler;

/**
 * The ScheduleKernel is the central place to define all of your application's
 * scheduled tasks.
 */
class ScheduleKernel
{
    protected Scheduler $scheduler;

    public function __construct(Scheduler $scheduler)
    {
        $this->scheduler = $scheduler;
    }

    /**
     * Define the application's command schedule.
     *
     * @param \Core\Console\Scheduling\Scheduler $schedule
     * @return void
     */
    protected function schedule(Scheduler $schedule): void
    {
        // Example:
        // $schedule->job(new \App\Jobs\PruneOldLogsJob)->cron('0 0 * * *'); // Runs daily at midnight
    }

    /**
     * Get the defined scheduled events.
     */
    public function getEvents(): array
    {
        $this->schedule($this->scheduler);
        return $this->scheduler->dueEvents();
    }
}
