<?php

namespace Core\ORM;

use Core\Application;
use Core\Database\CoroutineConnectionManager;
use Core\Database\Swoole\SwoolePdoPool;
use Core\Schema\Grammars\Grammar;
use Core\Schema\Grammars\MySqlGrammar;
use Core\Schema\Grammars\PostgresGrammar;
use Core\Schema\Grammars\SQLiteGrammar;
use Core\Schema\Grammars\SqlServerGrammar;
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

    /**
     * The array of active grammar instances.
     * @var array<string, \Core\Schema\Grammars\Grammar>
     */
    protected array $grammars = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function getDefaultConnection(): string
    {
        return $this->app->make('config')->get('database.default', 'mysql');
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
        $name ??= $this->getDefaultConnection();

        if (class_exists(SwoolePdoPool::class) && SwoolePdoPool::isInitialized()) {
            return $this->app->make(CoroutineConnectionManager::class)->get($name);
        }

        // Trong môi trường non-Swoole hoặc nếu pool không được cấu hình, chúng ta vẫn cần một kết nối.
        // Tuy nhiên, logic này không an toàn cho Swoole nếu pool bị tắt.
        // Thêm một cảnh báo để người phát triển biết về rủi ro.
        if ($this->app->runningInConsole() && php_sapi_name() === 'swoole') {
            trigger_error('Swoole PDO Pool is not initialized. Falling back to a persistent PDO connection which can cause issues in a Swoole environment. Please check your server.php config.', E_USER_WARNING);
        }

        // Để tránh leak, chúng ta sẽ không cache kết nối này trong thuộc tính $this->connections.
        // Mỗi lần gọi sẽ tạo kết nối mới trong môi trường non-pool.
        return $this->createFreshPdoConnection($name, $type);
    }

    /**
     * Manually release a connection back to the pool.
     * This is primarily for use in Swoole environments where a connection
     * might be held longer than a single coroutine's lifecycle.
     *
     * @param \PDO|\Swoole\Database\PDOProxy $connection The connection to release.
     * @param string|null $name The name of the pool to return the connection to.
     */
    public function release(mixed $connection, string $name = null): void
    {
        if (class_exists(SwoolePdoPool::class) && SwoolePdoPool::isInitialized()) {
            $configRepo = $this->app->make('config');
            $name ??= $configRepo->get('database.default', 'mysql');
            SwoolePdoPool::put($connection, $name);
        }
        // In non-Swoole environments, connections are typically persistent per-request or per-script and don't need to be manually released.
    }

    /**
     * Get a query builder instance for the given table.
     *
     * @param string $table
     * @return \Core\ORM\QueryBuilder
     */
    public function table(string $table): QueryBuilder
    {
        return (new QueryBuilder(''))->table($table);
    }

    public function getGrammar(string $name = null): Grammar
    {
        $name ??= $this->getDefaultConnection();

        if (isset($this->grammars[$name])) {
            return $this->grammars[$name];
        }

        $config = $this->getConfig($name);
        $driver = $config['driver'];

        $grammar = match ($driver) {
            'mysql' => new MySqlGrammar(),
            'pgsql' => new PostgresGrammar(),
            'sqlite' => new SQLiteGrammar(),
            'sqlsrv' => new SqlServerGrammar(),
            default => throw new \InvalidArgumentException("Unsupported database driver: {$driver}"),
        };

        return $this->grammars[$name] = $grammar;
    }

    protected function getConfig(string $name, ?string $type = null): array
    {
        $configRepo = $this->app->make('config');
        $connections = $configRepo->get('database.connections');
        $config = $connections[$name] ?? null;

        if (!$config) {
            throw new \Exception("Database connection [$name] not configured.");
        }

        if ($type && isset($config['read']) && isset($config['write'])) {
            if (!in_array($type, ['read', 'write'])) {
                throw new \InvalidArgumentException("Invalid connection type [{$type}]. Must be 'read' or 'write'.");
            }
            $typeSpecificConfig = $config[$type];
            unset($config['read'], $config['write']);
            $config = array_merge($config, $typeSpecificConfig);
        }

        return $config;
    }

    /**
     * Creates a new, non-pooled PDO connection.
     * This should only be used in non-Swoole environments or CLI scripts.
     *
     * @param string $name
     * @param string|null $type
     * @return \PDO
     * @throws \Exception
     */
    private function createFreshPdoConnection(string $name, ?string $type = null): \PDO
    {
        $config = $this->getConfig($name, $type);

        try {
            $dsn = $this->makeDsn($config);

            $defaultOptions = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_PERSISTENT => false, // Rất quan trọng khi không dùng pool
            ];
            $options = ($config['options'] ?? []) + $defaultOptions;

            return new \PDO(
                $dsn,
                $config['username'] ?? null,
                $config['password'] ?? null,
                $options,
            );
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Unknown database')) {
                throw new \RuntimeException("Database `{$config['database']}` does not exist. Please ensure it is created before starting the application.", (int)$e->getCode(), $e);
            }
            throw $e;
        }
    }

    /**
     * Close all connections in the pool.
     */
    public function flush(): void
    {
        $this->connections = [];
        $this->grammars = [];
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

    /**
     * Begin a transaction on the specified connection.
     *
     * @param string|null $name The connection name.
     * @return void
     * @throws \Exception
     */
    public function beginTransaction(string $name = null): void
    {
        $this->connection($name)->beginTransaction();
    }

    /**
     * Commit a transaction on the specified connection.
     *
     * @param string|null $name The connection name.
     * @return void
     * @throws \Exception
     */
    public function commit(string $name = null): void
    {
        $this->connection($name)->commit();
    }

    /**
     * Roll back a transaction on the specified connection.
     *
     * @param string|null $name The connection name.
     * @return void
     * @throws \Exception
     */
    public function rollBack(string $name = null): void
    {
        $this->connection($name)->rollBack();
    }

    /**
     * Check if a transaction is active on the specified connection.
     *
     * @param string|null $name The connection name.
     * @return bool
     * @throws \Exception
     */
    public function inTransaction(string $name = null): bool
    {
        return $this->connection($name)->inTransaction();
    }
}
