<?php

namespace Core\Database\Fiber;

use Core\Application;
use Core\Contracts\PoolManager;
use Core\ORM\Connection;
use PDO;

/**
 * Quản lý pool kết nối CSDL trong môi trường Fiber.
 */
class FiberConnectionManager implements PoolManager
{
    private FiberConnectionPool $pool;

    public function __construct(Application $app)
    {
        $config = $app->make('config');
        $poolSize = $config->get('server.swoole.db_pool.worker_pool_size', 10);

        $factory = function () use ($app): PDO {
            /** @var Connection $connection */
            $connection = $app->make(Connection::class);
            return $connection->createFreshConnection('mysql', 'write');
        };

        $this->pool = new FiberConnectionPool($factory, $poolSize);
    }

    /**
     * Lấy một kết nối PDO từ pool.
     * Phương thức này là non-blocking nhờ vào FiberConnectionPool.
     */
    public function get(): PDO
    {
        return $this->pool->get();
    }

    /**
     * Trả một kết nối PDO về lại pool.
     */
    public function put(PDO $connection): void
    {
        $this->pool->put($connection);
    }

    /**
     * Đóng tất cả kết nối trong pool.
     */
    public function close(): void
    {
        $this->pool->close();
    }

    /**
     * Làm ấm pool bằng cách tạo trước một số kết nối.
     */
    public function warmup(): void
    {
        $config = $this->app->make('config');
        $warmupSize = $config->get('server.swoole.db_pool.warmup_size', 2);
        $poolSize = $config->get('server.swoole.db_pool.worker_pool_size', 10);
        $targetSize = min($warmupSize, $poolSize);

        $connections = [];
        for ($i = 0; $i < $targetSize; $i++) {
            $connections[] = $this->get();
        }

        foreach ($connections as $connection) {
            $this->put($connection);
        }
    }
}
