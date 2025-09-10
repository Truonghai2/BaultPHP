<?php

namespace Core\Redis;

use Amp\Redis\RedisClient;
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

    /**
     * @param \Closure $factory Một hàm để tạo một instance RedisClient mới.
     * @param int $maxSize Số lượng kết nối tối đa cho phép trong pool.
     */
    public function __construct(
        private readonly \Closure $factory,
        private readonly int $maxSize = 10,
    ) {
        $this->pool = new SplQueue();
        $this->waitQueue = new SplQueue();
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
        if (!$this->pool->isEmpty()) {
            return $this->pool->dequeue();
        }

        if ($this->currentSize < $this->maxSize) {
            $this->currentSize++;
            try {
                return ($this->factory)()->await();
            } catch (Throwable $e) {
                $this->currentSize--;
                throw $e;
            }
        }

        $suspension = EventLoop::getSuspension();
        $this->waitQueue->enqueue($suspension);

        return $suspension->await();
    }

    /**
     * Trả một kết nối về lại pool.
     *
     * @param RedisClient $connection
     */
    public function put(RedisClient $connection): void
    {
        if (!$connection->isAlive()) {
            $this->currentSize--;
            return;
        }
        try {
            $connection->ping();
        } catch (Throwable) {
            $this->currentSize--;
            return;
        }
        if (!$this->waitQueue->isEmpty()) {
            $suspension = $this->waitQueue->dequeue();
            $suspension->resume($connection);
            return;
        }

        $this->pool->enqueue($connection);
    }

    /**
     * Đóng tất cả kết nối và hủy tất cả các Fiber đang chờ.
     */
    public function close(): void
    {
        while (!$this->waitQueue->isEmpty()) {
            $this->waitQueue->dequeue()->throw(new \RuntimeException('Pool is closing.'));
        }
        while (!$this->pool->isEmpty()) {
            /** @var RedisClient $connection */
            $connection = $this->pool->dequeue();
            $connection->close();
        }
        $this->currentSize = 0;
    }
}
