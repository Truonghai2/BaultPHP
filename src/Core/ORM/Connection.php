<?php

namespace Core\ORM;

use Core\Application;
use Core\Database\CoroutineConnectionManager;
use Core\Database\Swoole\SwoolePdoPool;
use PDOException;

/**
 * Connection manages database connections using a connection pool.
 * It supports multiple database configurations and handles connection creation.
 * NOTE: This class acts as a Database Manager and should be registered as a singleton.
 */
class Connection
{
    /**
     * The application instance.
     * @var \Core\Application
     */
    protected Application $app;

    /**
     * The array of active PDO connections.
     * @var array<string, \PDO>
     */
    protected array $connections = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get a PDO connection instance.
     *
     * @param string|null $name The connection name.
     * @param string      $type The connection type ('read' or 'write').
     * @return \PDO|\Swoole\Database\PDOProxy
     * @throws \Exception
     */
    public function connection(string $name = null, string $type = 'write'): mixed
    {
        $configRepo = $this->app->make('config');
        $name ??= $configRepo->get('database.default', 'mysql');

        // === SWOOLE CONNECTION POOL INTEGRATION ===
        // If the application is running in a Swoole environment and the pool is initialized,
        // we use the CoroutineConnectionManager to handle the connection lifecycle.
        // This is crucial for efficient and safe connection management in an async context.
        if (class_exists(SwoolePdoPool::class) && SwoolePdoPool::isInitialized()) {
            /** @var CoroutineConnectionManager $manager */
            $manager = $this->app->make(CoroutineConnectionManager::class);
            return $manager->get();
        }

        $poolKey = "{$name}::{$type}";

        if (isset($this->connections[$poolKey])) {
            return $this->connections[$poolKey];
        }

        $connections = $configRepo->get('database.connections');
        $originalConfig = $connections[$name] ?? null;

        if (!$originalConfig) {
            throw new \Exception("Database connection [$name] not configured.");
        }

        // Bắt đầu với một bản sao sạch của config cho lần resolve này
        $config = $originalConfig;

        // Xử lý tách biệt read/write
        if (isset($config['read']) && isset($config['write'])) {
            if (!in_array($type, ['read', 'write'])) {
                throw new \InvalidArgumentException("Loại kết nối không hợp lệ [{$type}]. Phải là 'read' hoặc 'write'.");
            }

            // Hợp nhất config cơ sở với config của loại kết nối cụ thể.
            // Config cụ thể (ví dụ: 'host') sẽ ghi đè lên config cơ sở.
            $typeSpecificConfig = $config[$type];
            unset($config['read'], $config['write']);
            $config = array_merge($config, $typeSpecificConfig);
        }

        $driver = $config['driver'];
        $dbname = $config['database'];

        try {
            $dsn = $this->makeDsn($config);

            $defaultOptions = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_PERSISTENT => false,
            ];
            $options = ($config['options'] ?? []) + $defaultOptions;

            $pdo = new \PDO(
                $dsn,
                $config['username'] ?? null,
                $config['password'] ?? null,
                $options,
            );

            $this->connections[$poolKey] = $pdo;
        } catch (PDOException $e) {
            // In a containerized environment, the application should not interactively
            // create the database. The database should be provisioned by the
            // infrastructure (e.g., via docker-compose environment variables).
            // If the database is not found, we throw a clear, fatal exception.
            if (str_contains($e->getMessage(), 'Unknown database')) {
                throw new \RuntimeException(
                    "Database `{$dbname}` does not exist. Please ensure it is created before starting the application. Check your docker-compose.yml and .env files.",
                    (int)$e->getCode(),
                    $e,
                );
            }

            throw $e;
        }

        return $this->connections[$poolKey];
    }

    /**
     * Close all connections in the pool.
     */
    public function flush(): void
    {
        $this->connections = [];
    }

    /**
     * Create a DSN string based on the database configuration.
     *
     * @param array $config
     * @return string
     * @throws \Exception
     */
    protected function makeDsn(array $config): string
    {
        $driver = $config['driver'];

        return match ($driver) {
            'mysql', 'pgsql' => sprintf(
                '%s:host=%s;port=%s;dbname=%s%s',
                $driver,
                $config['host'],
                $config['port'] ?? ($driver === 'mysql' ? 3306 : 5432),
                $config['database'],
                ($driver === 'mysql' ? ';charset=' . ($config['charset'] ?? 'utf8mb4') : ''),
            ),
            'sqlite' => 'sqlite:' . $config['database'],
            default => throw new \Exception("Unsupported driver [{$driver}]"),
        };
    }

}
