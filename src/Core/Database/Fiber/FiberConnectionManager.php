<?php

namespace Core\Database\Fiber;

use Core\Application;
use Core\Contracts\PoolManager;
use Core\ORM\Connection;
use InvalidArgumentException;
use PDO;

/**
 * Quản lý pool kết nối CSDL trong môi trường Fiber.
 */
class FiberConnectionManager implements PoolManager
{
    /** @var FiberConnectionPool[] */
    private array $pools = [];
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Lấy một kết nối PDO từ pool.
     * Phương thức này là non-blocking nhờ vào FiberConnectionPool.
     * @param string|null $name Tên của connection (ví dụ: 'mysql', 'pgsql').
     */
    public function get(?string $name = null): PDO
    {
        $name = $name ?? $this->getDefaultConnectionName();
        return $this->pool($name)->get();
    }

    /**
     * Trả một kết nối PDO về lại pool.
     * @param PDO $connection
     * @param string|null $name
     */
    public function put(PDO $connection, ?string $name = null): void
    {
        $name = $name ?? $this->getDefaultConnectionName();
        if (isset($this->pools[$name])) {
            $this->pools[$name]->put($connection);
        }
    }

    /**
     * Đóng tất cả kết nối trong pool.
     */
    public function close(): void
    {
        foreach ($this->pools as $pool) {
            $pool->close();
        }
        $this->pools = [];
    }

    /**
     * Làm ấm pool bằng cách tạo trước một số kết nối.
     */
    public function warmup(): void
    {
        $config = $this->app->make('config');
        $dbPoolsConfig = $config->get('server.swoole.db_pool.pools', []);

        foreach (array_keys($dbPoolsConfig) as $name) {
            $poolConfig = $config->get("server.swoole.db_pool.pools.{$name}", []);

            $isTaskWorker = false;
            if ($this->app->bound('swoole.server')) {
                $server = $this->app->make('swoole.server');
                $isTaskWorker = (bool) ($server->taskworker ?? false);
            }
            $poolSize = $isTaskWorker
                ? ($poolConfig['task_worker_pool_size'] ?? $config->get('server.swoole.db_pool.task_worker_pool_size', 10))
                : ($poolConfig['worker_pool_size'] ?? $config->get('server.swoole.db_pool.worker_pool_size', 10));

            $warmupSize = $isTaskWorker
                ? ($poolConfig['task_worker_warmup_size'] ?? $config->get('server.swoole.db_pool.task_worker_warmup_size', 5))
                : ($poolConfig['worker_warmup_size'] ?? $config->get('server.swoole.db_pool.worker_warmup_size', 5));

            $targetSize = min((int) $warmupSize, (int) $poolSize);

            if ($targetSize <= 0) {
                continue;
            }

            $connections = [];
            for ($i = 0; $i < $targetSize; $i++) {
                $connections[] = $this->get($name);
            }

            foreach ($connections as $connection) {
                $this->put($connection, $name);
            }
        }
    }

    /**
     * Lấy ra instance của pool cho một connection cụ thể.
     *
     * @param string $name
     * @return FiberConnectionPool
     */
    private function pool(string $name): FiberConnectionPool
    {
        if (!isset($this->pools[$name])) {
            $this->pools[$name] = $this->createPool($name);
        }

        return $this->pools[$name];
    }

    /**
     * Tạo một pool mới cho một connection.
     *
     * @param string $name
     * @return FiberConnectionPool
     */
    private function createPool(string $name): FiberConnectionPool
    {
        $config = $this->app->make('config');
        $dbConfig = $config->get("database.connections.{$name}");

        if (!$dbConfig) {
            throw new InvalidArgumentException("Database connection [{$name}] not configured.");
        }

        $poolConfig = $config->get("server.swoole.db_pool.pools.{$name}", []);

        $isTaskWorker = false;
        if ($this->app->bound('swoole.server')) {
            $server = $this->app->make('swoole.server');
            $isTaskWorker = (bool) ($server->taskworker ?? false);
        }

        $poolSize = $isTaskWorker
            ? ($poolConfig['task_worker_pool_size'] ?? $config->get('server.swoole.db_pool.task_worker_pool_size', 10))
            : ($poolConfig['worker_pool_size'] ?? $config->get('server.swoole.db_pool.worker_pool_size', 10));

        $factory = function () use ($name): PDO {
            /** @var Connection $connection */
            $connection = $this->app->make(Connection::class);
            return $connection->connection($name, 'write');
        };

        return new FiberConnectionPool($factory, (int) $poolSize);
    }

    private function getDefaultConnectionName(): string
    {
        return $this->app->make('config')->get('database.default', 'mysql');
    }
}
