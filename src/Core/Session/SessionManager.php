<?php

namespace Core\Session;

use Core\Contracts\Session\SessionHandlerInterface as Handler;
use Core\Contracts\Session\SessionInterface;
use Core\Contracts\StatefulService;
use Core\Manager;
use InvalidArgumentException;

/**
 * @mixin \Core\Session\Store
 */
class SessionManager extends Manager implements StatefulService
{
    /**
     * Tạo một instance của session driver.
     *
     * @param string|null $driver
     * @return \Core\Contracts\Session\SessionInterface
     */
    public function driver(string $driver = null): SessionInterface
    {
        return parent::driver($driver);
    }

    /**
     * Tạo một instance của session driver được chỉ định.
     *
     * @param string $driver
     * @return \Core\Contracts\Session\SessionInterface
     */
    protected function createDriver($driver): SessionInterface
    {
        $handler = $this->createHandler($driver);

        return new Store(
            $this->app->make('config')->get('session.cookie'),
            $handler,
        );
    }

    /**
     * Tạo một session handler dựa trên driver.
     *
     * @param string $driver
     * @return \Core\Contracts\Session\SessionHandlerInterface
     */
    protected function createHandler(string $driver): Handler
    {
        return match (strtolower($driver)) {
            'file' => $this->createFileHandler(),
            'database' => $this->createDatabaseHandler(),
            'redis' => $this->createRedisHandler(),
            default => throw new InvalidArgumentException("Unsupported session driver [{$driver}]."),
        };
    }

    protected function createFileHandler(): Handler
    {
        $path = $this->app->make('config')->get('session.files');
        return new FileSessionHandler($path);
    }

    protected function createDatabaseHandler(): Handler
    {
        $config = $this->app->make('config');
        $connectionName = $config->get('session.database_connection') ?? $config->get('database.default');
        $table = $config->get('session.table', 'sessions');
        $lifetime = (int) $config->get('session.lifetime', 120) * 60;

        // Use optimized handler nếu enable
        $useOptimized = $config->get('session.use_optimized_handler', true);
        
        if ($useOptimized) {
            return new OptimizedSwoolePdoSessionHandler($connectionName, $table, $lifetime);
        }

        return new SwoolePdoSessionHandler($connectionName, $table, $lifetime);
    }

    protected function createRedisHandler(): Handler
    {
        $config = $this->app->make('config');
        if (!$config->get('server.swoole.pools.redis.enabled', false)) {
            throw new \RuntimeException('Redis session driver requires the Redis connection pool to be enabled in config/server.php.');
        }

        $connectionName = $config->get('session.connection', 'default');
        $lifetime = (int) $config->get('session.lifetime', 120) * 60;

        return new SwooleRedisSessionHandler($connectionName, $lifetime);
    }

    public function getDefaultDriver(): string
    {
        return $this->app->make('config')->get('session.driver', 'file');
    }

    /**
     * Reset the state of the session manager.
     */
    public function resetState(): void
    {
        $this->drivers = [];
    }
}
