<?php

namespace Core\Queue\Drivers;

use Core\Application;
use Core\Contracts\Queue\Job;
use Core\Contracts\Queue\Queue;
use Core\Queue\Jobs\RedisJob;
use DateInterval;
use DateTimeInterface;

class RedisQueue implements Queue
{
    protected \Redis $redis;
    protected string $defaultQueue;
    protected string $connectionName;

    public function __construct(protected Application $app, protected array $config)
    {
        $manager = $this->app->make('redis');
        $this->connectionName = $this->config['connection'] ?? 'default';
        $this->redis = $manager->connection($this->connectionName);
        $this->defaultQueue = $config['queue'] ?? 'default';
    }

    public function push(Job $job, ?string $queue = null): void
    {
        $this->pushRaw($this->createPayload($job), $queue);
    }

    public function later($delay, Job $job, ?string $queue = null): void
    {
        $payload = $this->createPayload($job);
        $delaySeconds = $this->getSeconds($delay);

        $this->redis->zAdd(
            $this->getQueueName($queue) . ':delayed',
            time() + $delaySeconds,
            $payload,
        );
    }

    public function pop(?string $queue = null): ?Job
    {
        $queueName = $this->getQueueName($queue);
        // First, migrate any delayed jobs that are now due.
        $this->migrateExpiredJobs($queueName . ':delayed', $queueName);

        $payload = $this->redis->lPop($queueName);

        if ($payload) {
            // Instead of unserializing the job directly, we wrap it in a RedisJob instance.
            // This RedisJob instance will handle the lifecycle of the job (delete, release, fail).
            return new RedisJob(
                $this->app,
                $this->redis,
                $payload,
                $this->connectionName,
                $queueName,
            );
        }

        return null;
    }

    protected function pushRaw(string $payload, ?string $queue = null): void
    {
        $this->redis->rPush($this->getQueueName($queue), $payload);
    }

    protected function createPayload(Job $job): string
    {
        // Create a payload string that is compatible with the RedisJob class.
        // This structure is inspired by Laravel's queue payload.
        return json_encode([
            'uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'displayName' => get_class($job),
            'job' => 'Core\Queue\CallQueuedHandler@call', // Placeholder for handler
            'data' => [
                'commandName' => get_class($job),
                'command' => serialize($job),
            ],
            'attempts' => 0, // Initial attempt count is 0
        ]);
    }

    protected function getQueueName(?string $queue): string
    {
        return 'queues:' . ($queue ?? $this->defaultQueue);
    }

    /**
     * Get the total number of seconds for the given delay.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @return int
     */
    protected function getSeconds($delay): int
    {
        if ($delay instanceof DateInterval) {
            $delay = (new \DateTime())->add($delay)->getTimestamp() - time();
        }

        if ($delay instanceof DateTimeInterface) {
            return max(0, $delay->getTimestamp() - time());
        }

        return (int) $delay;
    }

    /**
     * Migrate the expired jobs from a ZSET to a list.
     * This is done atomically using a Lua script.
     *
     * @param  string  $from The name of the delayed queue (ZSET).
     * @param  string  $to   The name of the ready queue (LIST).
     * @return void
     */
    protected function migrateExpiredJobs(string $from, string $to): void
    {
        $script = <<<'LUA'
-- Get all jobs that are due (up to a limit of 100 to avoid blocking the server for too long)
local jobs = redis.call('zrangebyscore', KEYS[1], '-inf', ARGV[1], 'LIMIT', 0, 100)

-- If there are jobs to migrate...
if #jobs > 0 then
    -- Push them to the ready queue
    -- The 'unpack' function is used to pass the elements of the 'jobs' table as individual arguments to 'rpush'
    redis.call('rpush', KEYS[2], unpack(jobs))

    -- Remove them from the delayed queue
    redis.call('zremrangebyscore', KEYS[1], '-inf', ARGV[1])
end

return jobs
LUA;

        // The phpredis eval command signature is: eval(script, args_array, num_keys)
        // args_array contains keys first, then other arguments.
        // We have 2 keys (from, to) and 1 argument (time).
        $this->redis->eval($script, [$from, $to, time()], 2);
    }
}
