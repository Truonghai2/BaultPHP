<?php

namespace Core\Redis;

use Amp\Redis\RedisClient;
use Core\Application;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;
use SplQueue;
use Throwable;

/**
 * Quản lý một pool kết nối Redis bất đồng bộ sử dụng PHP Fibers.
 *
 * Class này cho phép nhiều Fiber cùng chia sẻ một số lượng kết nối giới hạn
 * mà không block lẫn nhau.
 */
class FiberRedisPool
{
    /** @var SplQueue<RedisClient> Các kết nối đang rảnh trong pool. */
    private SplQueue $pool;

    /** @var SplQueue<Suspension> Hàng đợi các Fiber đang chờ kết nối. */
    private SplQueue $waitQueue;

    /** @var int Số lượng kết nối đã được tạo. */
    private int $currentSize = 0;

    protected LoggerInterface $logger;

    /**
     * @param \Closure $factory Một hàm để tạo một instance RedisClient mới.
     * @param int $maxSize Số lượng kết nối tối đa cho phép trong pool.
     */
    public function __construct(
        private readonly \Closure $factory,
        private readonly int $maxSize = 10,
    ) {
        if ($this->maxSize <= 0) {
            throw new \LogicException('FiberRedisPool max size must be greater than 0. Please check your server configuration.');
        }

        $this->pool = new SplQueue();
        $this->waitQueue = new SplQueue();
        $this->logger = Application::getInstance()->make(LoggerInterface::class);
    }

    /**
     * Lấy một kết nối từ pool.
     *
     * Nếu không có kết nối nào rảnh và pool chưa đầy, một kết nối mới sẽ được tạo (bất đồng bộ).
     * Nếu pool đã đầy, Fiber hiện tại sẽ bị tạm dừng cho đến khi có kết nối được trả về.
     *
     * @return RedisClient
     */
    public function get(): RedisClient
    {
        $this->logger->debug(sprintf('FiberRedisPool: Attempting to get connection. Current size: %d, Max size: %d, Waiting Fibers: %d', $this->currentSize, $this->maxSize, $this->waitQueue->count()));

        while (!$this->pool->isEmpty()) {
            $connection = $this->pool->dequeue();
            try {
                $connection->ping();
                $this->logger->debug('FiberRedisPool: Reusing existing connection.');
                return $connection;
            } catch (Throwable $e) {
                $this->currentSize--;
                $this->logger->warning(sprintf('FiberRedisPool: Discarding invalid connection. Error: %s', $e->getMessage()));
            }
        }

        if ($this->currentSize < $this->maxSize) {
            $this->currentSize++;
            try {
                $this->logger->debug(sprintf('FiberRedisPool: Creating new connection. New size: %d', $this->currentSize));
                return ($this->factory)();
            } catch (Throwable $e) {
                $this->currentSize--;
                $this->logger->error(sprintf('FiberRedisPool: Failed to create new connection. Error: %s', $e->getMessage()));
                throw $e;
            }
        }

        $this->logger->debug('FiberRedisPool: Pool exhausted, suspending Fiber.');
        $suspension = EventLoop::getSuspension();
        $this->waitQueue->enqueue($suspension);

        try {
            $connection = $suspension->suspend();
            $this->logger->debug('FiberRedisPool: Fiber resumed, connection obtained from wait queue.');
            return $connection;
        } catch (Throwable $e) {
            $this->logger->error(sprintf('FiberRedisPool: Fiber suspended but threw exception on resume. Error: %s', $e->getMessage()));
            throw $e;
        }
    }

    /**
     * Trả một kết nối về lại pool.
     *
     * @param RedisClient $connection
     */
    public function put(RedisClient $connection): void
    {
        $this->logger->debug(sprintf('FiberRedisPool: Attempting to put connection. Current size: %d, Waiting Fibers: %d', $this->currentSize, $this->waitQueue->count()));

        try {
            $connection->ping();
        } catch (Throwable $e) {
            $this->currentSize--;
            $this->logger->warning(sprintf('FiberRedisPool: Discarding connection on put due to ping failure. Error: %s', $e->getMessage()));
            return;
        }

        if (!$this->waitQueue->isEmpty()) {
            $suspension = $this->waitQueue->dequeue();
            $this->logger->debug('FiberRedisPool: Resuming waiting Fiber.');
            $suspension->resume($connection);
            return;
        }

        $this->pool->enqueue($connection);
        $this->logger->debug('FiberRedisPool: Connection returned to pool.');
    }

    /**
     * Đóng tất cả kết nối và hủy tất cả các Fiber đang chờ.
     */
    public function close(): void
    {
        $this->logger->info(sprintf('FiberRedisPool: Closing pool. Waiting Fibers: %d, Connections in pool: %d', $this->waitQueue->count(), $this->pool->count()));
        while (!$this->waitQueue->isEmpty()) {
            $this->waitQueue->dequeue()->throw(new \RuntimeException('Pool is closing.'));
        }
        while (!$this->pool->isEmpty()) {
            /** @var RedisClient $connection */
            $connection = $this->pool->dequeue();
            $connection->close();
        }
        $this->currentSize = 0;
        $this->logger->info('FiberRedisPool: Pool closed.');
    }
}
