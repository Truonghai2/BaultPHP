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

        if (class_exists(SwoolePdoPool::class) && SwoolePdoPool::isInitialized()) {
            /** @var CoroutineConnectionManager $manager */
            $manager = $this->app->make(CoroutineConnectionManager::class);
            return $manager->get();
        }

        $poolKey = "{$name}::{$type}";

        if (isset($this->connections[$poolKey])) {
            return $this->connections[$poolKey];
        }

        $config = $this->getConfig($name, $type);

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
            if (str_contains($e->getMessage(), 'Unknown database')) {
                throw new \RuntimeException(
                    "Database `{$config['database']}` does not exist. Please ensure it is created before starting the application. Check your docker-compose.yml and .env files.",
                    (int)$e->getCode(),
                    $e,
                );
            }

            throw $e;
        }

        return $this->connections[$poolKey];
    }

    public function getGrammar(string $name = null): Grammar
    {
        $configRepo = $this->app->make('config');
        $name ??= $configRepo->get('database.default', 'mysql');

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

}
