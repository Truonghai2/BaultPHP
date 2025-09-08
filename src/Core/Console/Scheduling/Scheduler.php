<?php

namespace Core\Console\Scheduling;

use Core\Contracts\Queue\Job;

/**
 * The main scheduler service used to define scheduled tasks.
 */
class Scheduler
{
    /**
     * The array of scheduled events.
     *
     * @var \Core\Console\Scheduling\ScheduledEvent[]
     */
    protected array $events = [];

    /**
     * Schedule a new job.
     *
     * @param \Core\Contracts\Queue\Job $job
     * @return \Core\Console\Scheduling\ScheduledEvent
     */
    public function job(Job $job): ScheduledEvent
    {
        $event = new ScheduledEvent($job);
        $this->events[] = $event;
        return $event;
    }

    /**
     * Get all of the events that are due to run.
     *
     * @return \Core\Console\Scheduling\ScheduledEvent[]
     */
    public function dueEvents(): array
    {
        return array_filter($this->events, function (ScheduledEvent $event) {
            return $event->isDue();
        });
    }
}
