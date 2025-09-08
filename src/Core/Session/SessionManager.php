<?php

namespace Core\Session;

use Core\Application;
use Core\Contracts\StatefulService;
use Core\Database\Swoole\SwooleRedisPool;
use SessionHandlerInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

class SessionManager implements StatefulService
{
    protected Application $app;
    protected array $config;

    /**
     * The current session instance.
     * This is cached for the duration of a single request.
     *
     * @var Session|null
     */
    protected ?Session $session = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->config = $app->make('config')->get('session');
    }

    /**
     * Get the session instance for the current request.
     *
     * If a session instance does not exist, it will be created.
     *
     * @return Session
     */
    public function getSession(): Session
    {
        if ($this->session === null) {
            $this->session = $this->buildSession();
        }

        return $this->session;
    }

    /**
     * Reset the state of the session manager.
     *
     * This is called by the Swoole server after each request to prevent
     * session state from leaking into the next request. It nullifies the
     * cached session instance, forcing a new one to be created.
     */
    public function resetState(): void
    {
        $this->session = null;
    }

    protected function buildSession(): Session
    {
        $handler = $this->createDriver($this->config['driver'] ?? 'file');
        $storage = new NativeSessionStorage([], $handler);

        return new Session($storage);
    }

    protected function createDriver(string $driver): SessionHandlerInterface
    {
        return match ($driver) {
            'redis' => $this->createRedisDriver(),
            'database' => $this->createDatabaseDriver(),
            default => $this->createFileDriver(),
        };
    }

    protected function createFileDriver(): SessionHandlerInterface
    {
        return new NativeFileSessionHandler(
            $this->config['files'] ?? storage_path('framework/sessions'),
        );
    }

    protected function createDatabaseDriver(): SessionHandlerInterface
    {
        // Sử dụng PdoSessionHandler của Symfony để đảm bảo có cơ chế khóa (locking) an toàn
        return new PdoSessionHandler(
            $this->app->make(\PDO::class),
            [
                'db_table' => $this->config['table'] ?? 'sessions',
                'db_id_col' => 'id',
                'db_data_col' => 'payload',
                'db_time_col' => 'last_activity',
                'db_lifetime_col' => 'lifetime',
            ],
        );
    }

    protected function createRedisDriver(): SessionHandlerInterface
    {
        $isSwooleEnv = extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0;

        if ($isSwooleEnv && class_exists(SwooleRedisPool::class) && SwooleRedisPool::isInitialized()) {
            // RedisSessionHandler đã được viết tốt cho môi trường Swoole
            return new RedisSessionHandler($this->config);
        }

        throw new \RuntimeException('Redis session driver is configured but is only supported in a Swoole environment with a configured Redis pool.');
    }
}
