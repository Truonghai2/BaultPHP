<?php

namespace Core\Queue\Jobs;

use Core\Contracts\Queue\Job;
use Core\Contracts\Task\Task;
use Core\Queue\QueueWorker;
use Psr\Log\LoggerInterface;

/**
 * Class ProcessJobTask
 *
 * This task acts as a mini-worker responsible for executing a job.
 * It contains the logic for handling job execution, retries, and failures.
 */
class ProcessJobTask implements Task
{
    /**
     * The job instance to be processed.
     *
     * @var Job|BaseJob
     */
    public Job $job;

    /**
     * @param Job $job The job to process.
     */
    public function __construct(Job $job)
    {
        $this->job = $job;
    }

    /**
     * Handles the execution of the job within a Swoole Task Worker.
     * This method now delegates the entire processing logic to the QueueWorker,
     * unifying job handling for both Swoole tasks and traditional CLI workers.
     *
     * @param QueueWorker $worker The queue worker instance, injected by the container.
     * @param LoggerInterface $logger
     * @return void
     */
    public function handle(QueueWorker $worker, LoggerInterface $logger): void
    {
        $logger->debug('Handing off job to QueueWorker inside ProcessJobTask.', ['job' => get_class($this->job)]);
        // The connection name 'swoole' is a logical name for jobs processed via tasks.
        $worker->process('swoole', $this->job);
    }
}
