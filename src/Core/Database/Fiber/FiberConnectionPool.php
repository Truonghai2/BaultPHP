<?php

namespace Core\Database\Fiber;

use PDO;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;
use SplQueue;
use Throwable;

/**
 * Quản lý một pool kết nối PDO bất đồng bộ sử dụng PHP Fibers.
 *
 * Class này cho phép nhiều Fiber cùng chia sẻ một số lượng kết nối giới hạn
 * mà không block lẫn nhau.
 */
class FiberConnectionPool
{
    /** @var SplQueue<PDO> Các kết nối đang rảnh trong pool. */
    private SplQueue $pool;

    /** @var SplQueue<Suspension> Hàng đợi các Fiber đang chờ kết nối. */
    private SplQueue $waitQueue;

    /** @var int Số lượng kết nối đã được tạo. */
    private int $currentSize = 0;

    /**
     * @param \Closure $factory Một hàm để tạo một instance PDO mới.
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
     * Nếu không có kết nối nào rảnh và pool chưa đầy, một kết nối mới sẽ được tạo.
     * Nếu pool đã đầy, Fiber hiện tại sẽ bị tạm dừng cho đến khi có kết nối được trả về.
     *
     * @return PDO
     */
    public function get(): PDO
    {
        if (!$this->pool->isEmpty()) {
            return $this->pool->dequeue();
        }

        if ($this->currentSize < $this->maxSize) {
            $this->currentSize++;
            try {
                return ($this->factory)();
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
     * Nếu có một Fiber đang chờ, kết nối sẽ được đưa trực tiếp cho Fiber đó.
     * Nếu không, kết nối sẽ được đưa vào danh sách rảnh.
     *
     * @param PDO $connection
     */
    public function put(PDO $connection): void
    {
        try {
            $connection->getAttribute(PDO::ATTR_SERVER_INFO);
        } catch (\PDOException $e) {
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
            $this->pool->dequeue();
        }
        $this->currentSize = 0;
    }
}
