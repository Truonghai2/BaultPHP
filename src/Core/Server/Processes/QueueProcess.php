<?php

namespace Core\Server\Processes;

use Core\Contracts\Queue\Job;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Process;
use Throwable;

class QueueProcess extends BaseProcess
{
    private Channel $jobChannel;

    public function run(Process $worker): void
    {
        $this->logger->info('QueueProcess started.', ['pid' => $worker->pid]);
        swoole_set_process_name('bault:queue_process');

        $this->jobChannel = new Channel(1024);

        Coroutine::create(function () use ($worker) {
            while ($data = $worker->read()) {
                $message = @unserialize($data);
                if (is_array($message) && $message['type'] === 'queue_job' && $message['payload'] instanceof Job) {
                    $this->logger->info('QueueProcess received a new job.', ['job' => get_class($message['payload'])]);
                    $this->jobChannel->push($message['payload']);
                }
            }
        });

        Coroutine::create(function () {
            while (true) {
                /** @var Job $job */
                $job = $this->jobChannel->pop();

                if ($job) {
                    $this->processJob($job);
                }
            }
        });
    }

    /**
     * Logic xử lý một job.
     */
    private function processJob(Job $job): void
    {
        try {
            $jobClass = get_class($job);
            $this->logger->info("Processing job: {$jobClass}");

            // Trong một ứng dụng thực tế, bạn sẽ gọi $job->handle()
            // Ở đây chúng ta chỉ log message của nó
            $this->logger->info('Job payload message: ' . ($job->message ?? 'N/A'));

        } catch (Throwable $e) {
            $this->logger->error('Failed to process job: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
