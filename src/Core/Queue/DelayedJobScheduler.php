<?php

namespace Core\Queue;

use Amp\Future;
use Core\Application;
use Core\Contracts\Queue\Job;
use Core\Queue\Jobs\ProcessJobTask;
use Core\Redis\FiberRedisManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Handles the logic for polling and dispatching delayed jobs from a sorted set in Redis.
 * This class is designed to be run periodically by a Swoole timer.
 */
class DelayedJobScheduler
{
    protected array $config;
    protected string $queueName;
    protected FiberRedisManager $redisManager;
    protected string $redisConnectionName;
    protected int $batchSize;

    public function __construct(
        protected Application $app,
        protected LoggerInterface $logger,
    ) {
        $this->config = $this->app->make('config')->get('queue');
        $this->redisConnectionName = $this->config['connections']['redis']['connection'] ?? 'default';
        $this->queueName = $this->config['connections']['redis']['delayed_queue'] ?? 'queues:delayed:' . $this->redisConnectionName;
        $this->batchSize = $this->config['scheduler']['batch_size'] ?? 100;
        $this->redisManager = $this->app->make('redis');
    }

    /**
     * Điểm vào chính được gọi bởi Swoole timer.
     * Quét và xử lý các job bị trễ theo từng lô.
     */
    public function __invoke(): void
    {
        $redis = null;

        try {
            $this->logger->debug('Attempting to get Redis connection for DelayedJobScheduler.');
            $redis = $this->redisManager->getForScheduler($this->redisConnectionName);
            $this->logger->debug('Successfully obtained Redis connection.');

            try {
                $jobsToProcess = $this->fetchAndRemoveDueJobs($redis);
                $this->logger->debug('fetchAndRemoveDueJobs completed.');
            } catch (Throwable $e) {
                $this->logger->error('Error during fetchAndRemoveDueJobs: ' . $e->getMessage(), ['exception' => $e]);
                throw $e; // Re-throw to be caught by the outer catch
            }

            if (empty($jobsToProcess)) {
                $this->logger->debug('No delayed jobs to process.');
                return; // Không có job nào, kết thúc lần chạy này. `finally` sẽ dọn dẹp.
            }

            $this->logger->info(sprintf('Processing %d delayed jobs.', count($jobsToProcess)));

            foreach ($jobsToProcess as $serializedJob) {
                $job = unserialize($serializedJob);
                if ($job instanceof Job) {
                    /** @var \Core\Server\SwooleServer $server */
                    $server = $this->app->make(\Core\Server\SwooleServer::class);
                    $server->dispatchTask(new ProcessJobTask($job));
                }
            }
        } catch (Throwable $e) {
            $this->logger->error('Delayed job scheduler error: ' . $e->getMessage(), ['exception' => $e]);
        } finally {
            if ($redis) {
                $this->logger->debug('Returning Redis connection to pool.', ['connection_name' => $this->redisConnectionName]);
                $this->redisManager->put($redis, $this->redisConnectionName);
                $this->logger->debug('Redis connection returned to pool.');
            } else {
                $this->logger->warning('Redis connection was null in finally block for DelayedJobScheduler.');
            }
        }
    }

    /**
     * Lấy và xóa các job đã đến hạn một cách nguyên tử bằng Lua script.
     *
     * @param \Amp\Redis\RedisClient $redis
     * @return Future<array<string>>
     */
    private function fetchAndRemoveDueJobs(\Amp\Redis\RedisClient $redis): array
    {
        $script = <<<LUA
            local jobs = redis.call('zrangebyscore', KEYS[1], '-inf', ARGV[1], 'LIMIT', 0, ARGV[2])
            if #jobs > 0 then
                redis.call('zrem', KEYS[1], unpack(jobs))
            end
            return jobs
LUA;

        return $redis->eval($script, [$this->queueName], [time(), $this->batchSize]);
    }
}
