<?php

namespace Core\ORM;

use PDO;
use PDOException;

/**
 * Connection manages database connections using a connection pool.
 * It supports multiple database configurations and handles connection creation.
 */
class Connection
{
    /**
     * @var array<string, PDO>
     */
    protected static array $pool = [];

    /**
     * Get a PDO connection instance.
     *
     * @param string|null $name
     * @return PDO
     * @throws \Exception
     */    
    public static function get(string $name = null): PDO
    {
        $name ??= config('database.default', 'mysql');

        if (isset(static::$pool[$name])) {
            return static::$pool[$name];
        }

        $connections = config('database.connections');
        $config = $connections[$name] ?? null;

        if (!$config) {
            throw new \Exception("Database connection [$name] not configured.");
        }

        $driver = $config['driver'];
        $dbname = $config['database'];

        try {
            $dsn = self::makeDsn($config);
            $pdo = new PDO(
                $dsn,
                $config['username'] ?? null,
                $config['password'] ?? null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT => false,
                ]
            );

            static::$pool[$name] = $pdo;
        } catch (PDOException $e) {
            if (
                $driver === 'mysql' &&
                str_contains($e->getMessage(), "Unknown database")
            ) {
                echo "Database `$dbname` không tồn tại.\n";
                echo "Bạn có muốn tạo mới không? (y/n): ";
                $answer = strtolower(trim(fgets(STDIN)));

                if ($answer === 'y') {
                    self::createDatabase($config);
                    return self::get($name);
                }

                throw new \Exception("Quá trình bị huỷ. Cơ sở dữ liệu chưa được tạo.");
            }

            throw $e;
        }

        return static::$pool[$name];
    }

    /**
     * Close all connections in the pool.
     */
    public static function flush(): void
    {
        static::$pool = [];
    }

    /**
     * Create a DSN string based on the database configuration.
     *
     * @param array $config
     * @return string
     * @throws \Exception
     */
    protected static function makeDsn(array $config): string
    {
        return match ($config['driver']) {
            'mysql' => "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}",
            'pgsql' => "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']};user={$config['username']};password={$config['password']}",
            'sqlite' => "sqlite:{$config['database']}",
            default => throw new \Exception("Unsupported driver [{$config['driver']}]"),
        };
    }

    /**
     * Create a new database if it does not exist.
     *
     * @param array $config
     * @throws \Exception
     */
    protected static function createDatabase(array $config): void
    {
        $dsn = "mysql:host={$config['host']};port={$config['port']};charset={$config['charset']}";
        $pdo = new PDO(
            $dsn,
            $config['username'] ?? null,
            $config['password'] ?? null,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['database']}` CHARACTER SET {$config['charset']} COLLATE utf8mb4_unicode_ci");

        echo "Database `{$config['database']}` đã được tạo thành công.\n";
    }
}
