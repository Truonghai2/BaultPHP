<?php

namespace Core\Queue\Drivers;

use Core\Application;
use Core\Contracts\Queue\Job;
use Core\Contracts\Queue\Queue;
use Core\Redis\RedisManager;
use DateInterval;
use DateTimeInterface;

class RedisQueue implements Queue
{
    protected \Redis $redis;
    protected string $defaultQueue;

    public function __construct(protected Application $app, protected array $config)
    {
        // Lấy ra RedisManager từ container
        $manager = $this->app->make(RedisManager::class);
        // Lấy ra tên connection từ config của queue, fallback về 'default'
        $connectionName = $this->config['connection'] ?? 'default';
        // Lấy instance Redis cho connection cụ thể đó
        $this->redis = $manager->connection($connectionName);
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
        // First, migrate any delayed jobs that are now due.
        $this->migrateExpiredJobs($this->getQueueName($queue) . ':delayed', $this->getQueueName($queue));

        $payload = $this->redis->lPop($this->getQueueName($queue));

        if ($payload) {
            $payloadData = json_decode($payload, true);

            // An toàn hơn: Kiểm tra xem class có tồn tại và có implement Job không
            $jobClass = $payloadData['job_class'] ?? null;
            if ($jobClass && class_exists($jobClass) && is_subclass_of($jobClass, Job::class)) {
                // Tái tạo job từ dữ liệu an toàn (JSON) thay vì unserialize
                // Giả định job có một constructor hoặc một phương thức tĩnh để tạo từ dữ liệu
                // Ví dụ: return $jobClass::fromQueue($payloadData['data']);
                // Ở đây, chúng ta sẽ giả định constructor chấp nhận dữ liệu
                return new $jobClass($payloadData['data']);
            }

            // Nếu payload không hợp lệ, ghi log và bỏ qua
            $this->app->make('log')->error('Invalid job payload received', ['payload' => $payload]);
            return null;
        }

        return null;
    }

    protected function pushRaw(string $payload, ?string $queue = null): void
    {
        $this->redis->rPush($this->getQueueName($queue), $payload);
    }

    protected function createPayload(Job $job): string
    {
        // Thay thế serialize() bằng một cấu trúc JSON an toàn.
        // Job cần có một phương thức để lấy dữ liệu có thể tuần tự hóa an toàn.
        // Ví dụ: public function getQueueableData(): array { return ['userId' => $this->userId]; }
        $data = method_exists($job, 'getQueueableData') ? $job->getQueueableData() : (array) $job;

        return json_encode([
            'job_class' => get_class($job),
            'data'      => $data,
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
