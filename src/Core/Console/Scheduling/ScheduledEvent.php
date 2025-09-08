<?php

namespace Core\Console\Scheduling;

use Core\Contracts\Queue\Job;
use Cron\CronExpression;

/**
 * Represents a single task that has been scheduled.
 */
class ScheduledEvent
{
    /**
     * The cron expression for the schedule.
     *
     * @var string
     */
    protected string $expression = '* * * * *';

    /**
     * The job to be executed.
     *
     * @var \Core\Contracts\Queue\Job
     */
    protected Job $job;

    public function __construct(Job $job)
    {
        $this->job = $job;
    }

    /**
     * Set the cron expression for the event.
     *
     * @param string $expression
     * @return $this
     */
    public function cron(string $expression): self
    {
        $this->expression = $expression;
        return $this;
    }

    /**
     * Check if the event is due to run.
     *
     * @return bool
     */
    public function isDue(): bool
    {
        return (new CronExpression($this->expression))->isDue();
    }

    /**
     * Get the job associated with this event.
     *
     * @return \Core\Contracts\Queue\Job
     */
    public function getJob(): Job
    {
        return $this->job;
    }
}
