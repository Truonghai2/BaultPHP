<?php

namespace Core\Session;

use Core\Contracts\StatefulService;
use Core\Manager;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

/**
 * @mixin \Symfony\Component\HttpFoundation\Session\Session
 */
class SessionManager extends Manager implements StatefulService
{
    /**
     * Get a session driver instance.
     */
    public function driver(string $driver = null): SessionInterface // Override để có type-hint tốt hơn
    {
        $driver = $driver ?: $this->getDefaultDriver();

        return $this->drivers[$driver] ??= $this->createDriver($driver);
    }

    protected function createDriver(string $driver): SessionInterface
    {
        // Logic của Manager.php đã xử lý custom creators và gọi create...Driver
        // nên chúng ta chỉ cần tập trung vào việc tạo handler.
        // Tuy nhiên, vì cấu trúc của bạn khác một chút, chúng ta sẽ override createDriver
        // và không dùng các phương thức create<Name>Driver của Manager.

        $handler = $this->createHandler($driver);
        $storage = new NativeSessionStorage([], $handler);

        return new Session($storage);
    }

    protected function createHandler(string $driver): \SessionHandlerInterface
    {
        return match (strtolower($driver)) {
            'database' => $this->createDatabaseHandler(),
            'redis' => $this->createRedisHandler(),
            'file' => $this->createFileHandler(),
            default => throw new InvalidArgumentException("Unsupported session driver [{$driver}]."),
        };
    }

    protected function createDatabaseHandler(): SwooleCompatiblePdoSessionHandler
    {
        $config = $this->app->make('config');
        if (!$config->get('server.swoole.pools.database.enabled', false)) {
            throw new \RuntimeException('Database session driver requires the DB connection pool to be enabled in config/server.php.');
        }

        $connectionName = $config->get('session.database_connection') ?? $config->get('database.default');
        $table = $config->get('session.table', 'sessions');
        $lifetimeInMinutes = (int) $config->get('session.lifetime', 120);

        return new SwooleCompatiblePdoSessionHandler(
            $connectionName,
            [
                'db_table' => $table,
                'ttl' => $lifetimeInMinutes * 60,
            ],
        );
    }

    protected function createRedisHandler(): SwooleRedisSessionHandler
    {
        $config = $this->app->make('config');
        if (!$config->get('server.swoole.pools.redis.enabled', false)) {
            throw new \RuntimeException('Redis session driver requires the Redis connection pool to be enabled in config/server.php.');
        }

        $connectionName = $config->get('session.connection', 'default');
        $lifetime = (int) $config->get('session.lifetime', 120) * 60;

        return new SwooleRedisSessionHandler(
            $connectionName,
            $lifetime,
        );
    }

    protected function createFileHandler(): NativeFileSessionHandler
    {
        $path = $this->app->make('config')->get('session.files');
        return new NativeFileSessionHandler($path);
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
