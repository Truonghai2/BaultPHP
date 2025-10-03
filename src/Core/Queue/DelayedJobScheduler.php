<?php

namespace Core\Queue;

use Amp\Future;
use Core\Application;
use Core\Logging\Processor\ContextProcessor;
use Core\Queue\Jobs\ProcessDelayedJobBatchTask;
use Core\Redis\FiberRedisManager;
use Monolog\Logger;
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
        $this->redisConnectionName = $this->config['connections']['redis']['connection'] ?? 'queue';
        $this->queueName = $this->config['connections']['redis']['delayed_queue'] ?? 'queues:delayed:' . $this->redisConnectionName;
        $this->batchSize = $this->config['scheduler']['batch_size'] ?? 100;
        $this->redisManager = $this->app->make(FiberRedisManager::class);

        if ($this->logger instanceof Logger) {
            $this->logger->pushProcessor(new ContextProcessor(['source' => 'scheduler']));
        }
    }

    /**
     * Điểm vào chính được gọi bởi Swoole timer.
     * Quét và xử lý các job bị trễ theo từng lô.
     */
    public function __invoke(): void
    {
        $jobsToProcess = [];

        $redis = null;
        try {
            $this->logger->debug('Attempting to get Redis connection for DelayedJobScheduler.');
            $redis = $this->redisManager->getForScheduler($this->redisConnectionName);

            if (!$redis) {
                $this->logger->warning('Could not obtain Redis connection for scheduler. Skipping this run.');
                return;
            }

            $this->logger->debug('Successfully obtained Redis connection.');

            $jobsToProcess = $this->fetchAndRemoveDueJobs($redis);
            $this->logger->debug('fetchAndRemoveDueJobs completed.');
        } catch (Throwable $e) {
            $this->logger->error('Failed to fetch delayed jobs from Redis: ' . $e->getMessage(), ['exception' => $e]);
        } finally {
            if ($redis) {
                $this->logger->debug('Returning Redis connection to pool.', ['connection_name' => $this->redisConnectionName]);
                $this->redisManager->put($redis, $this->redisConnectionName);
                $this->logger->debug('Redis connection returned to pool.');
            } else {
                $this->logger->debug('Redis connection was null, no need to return to pool.');
            }
        }

        if (empty($jobsToProcess)) {
            $this->logger->debug('No delayed jobs to process.');
            return;
        }

        $this->logger->info(sprintf('Dispatching a batch of %d delayed jobs to a task worker.', count($jobsToProcess)));

        /** @var \Core\Server\SwooleServer $server */
        $server = $this->app->make(\Core\Server\SwooleServer::class);

        // Cải tiến hiệu năng: Thay vì lặp và dispatch từng job,
        // chúng ta dispatch cả lô job cho một task worker duy nhất xử lý.
        // Điều này giải phóng scheduler để nó có thể đi lấy lô tiếp theo ngay lập tức.
        $server->dispatchTask(new ProcessDelayedJobBatchTask($jobsToProcess));
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
