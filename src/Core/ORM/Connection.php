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
     * The array of active connections.
     * @var array<string, mixed>
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
     * Get a connection instance.
     *
     * @param string|null $name The connection name.
     * @param string      $type The connection type ('read' or 'write').
     * @return mixed
     * @throws \Exception
     */
    public function connection(string $name = null, string $type = 'write'): mixed
    {
        $name ??= $this->getDefaultConnection();

        $config = $this->getConfig($name);

        if (($config['driver'] ?? '') === 'mongodb') {
            return $this->resolveMongoConnection($name, $config);
        }

        if (class_exists(SwoolePdoPool::class) && SwoolePdoPool::isInitialized()) {
            return $this->app->make(CoroutineConnectionManager::class)->get($name);
        }

        if ($this->app->runningInConsole() && php_sapi_name() === 'swoole') {
            trigger_error('Swoole PDO Pool is not initialized. Falling back to a persistent PDO connection which can cause issues in a Swoole environment. Please check your server.php config.', E_USER_WARNING);
        }

        return $this->createFreshPdoConnection($name, $type);
    }

    /**
     * Resolve a MongoDB connection.
     */
    protected function resolveMongoConnection(string $name, array $config): \Core\Database\MongoConnection
    {
        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        return $this->connections[$name] = new \Core\Database\MongoConnection($config);
    }

    /**
     * Manually release a connection back to the pool.
     *
     * @param mixed $connection The connection to release.
     * @param string|null $name The name of the pool to return the connection to.
     */
    public function release(mixed $connection, string $name = null): void
    {
        if (class_exists(SwoolePdoPool::class) && SwoolePdoPool::isInitialized()) {
            $configRepo = $this->app->make('config');
            $name ??= $configRepo->get('database.default', 'mysql');
            SwoolePdoPool::put($connection, $name);
        }
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
     */
    private function createFreshPdoConnection(string $name, ?string $type = null): \PDO
    {
        $config = $this->getConfig($name, $type);

        try {
            $dsn = $this->makeDsn($config);

            $defaultOptions = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_PERSISTENT => false,
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
                throw new \RuntimeException("Database `{$config['database']}` does not exist.", (int)$e->getCode(), $e);
            }
            throw $e;
        }
    }

    public function flush(): void
    {
        $this->connections = [];
        $this->grammars = [];
    }

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

    public function beginTransaction(string $name = null): void
    {
        $this->connection($name)->beginTransaction();
    }

    public function commit(string $name = null): void
    {
        $this->connection($name)->commit();
    }

    public function rollBack(string $name = null): void
    {
        $this->connection($name)->rollBack();
    }

    public function inTransaction(string $name = null): bool
    {
        return $this->connection($name)->inTransaction();
    }
}
