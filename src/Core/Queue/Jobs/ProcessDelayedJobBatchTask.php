<?php

namespace Core\Queue\Jobs;

use Core\Contracts\Queue\Job;
use Core\Contracts\Task\Task;
use Core\Server\SwooleServer;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Task này xử lý một lô (batch) các job bị trễ.
 * Thay vì dispatch từng job một từ scheduler, chúng ta gửi cả lô
 * để giảm overhead và xử lý song song trong một task worker.
 */
class ProcessDelayedJobBatchTask implements Task
{
    /**
     * @param array<string> $serializedJobs Mảng các job đã được serialize.
     */
    public function __construct(
        public array $serializedJobs,
    ) {
    }

    /**
     * @param SwooleServer $server
     * @param LoggerInterface $logger
     */
    public function handle(SwooleServer $server, LoggerInterface $logger): void
    {
        $logger->debug(sprintf('Task worker is processing a batch of %d delayed jobs.', count($this->serializedJobs)));

        foreach ($this->serializedJobs as $serializedJob) {
            try {
                $job = unserialize($serializedJob, [
                    'allowed_classes' => fn (string $class) => is_a($class, Job::class, true),
                ]);

                if ($job instanceof Job) {
                    $server->dispatchTask(new ProcessJobTask($job));
                } else {
                    $logger->warning('A non-job payload was found in the delayed job batch.', ['payload' => $serializedJob]);
                }
            } catch (Throwable $e) {
                $logger->error('Failed to unserialize a delayed job from a batch. The job will be lost.', [
                    'exception' => $e,
                    'payload' => $serializedJob,
                ]);
            }
        }
    }
}
