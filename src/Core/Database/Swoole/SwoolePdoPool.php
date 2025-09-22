<?php

namespace Core\Database\Swoole;

use PDO;
use Throwable;

/**
 * Manages a pool of PDO connections using a custom channel-based pool.
 * This class extends BaseSwoolePool to provide PDO-specific logic for
 * creating, pinging, and validating connections.
 */
class SwoolePdoPool extends BaseSwoolePool
{
    private static ?\WeakMap $lastUsedTimes = null;

    protected static function createConnection(string $name): mixed
    {
        self::$lastUsedTimes ??= new \WeakMap();
        $config = static::$configs[$name];
        $driver = $config['driver'];
        $host = $config['write']['host'] ?? $config['host'];
        $port = $config['port'];
        $database = $config['database'];

        $dsn = "{$driver}:host={$host};port={$port};dbname={$database}";

        if ($driver === 'mysql' && !empty($config['charset'])) {
            $dsn .= ';charset=' . $config['charset'];
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
            self::$lastUsedTimes[$pdo] = time();
            return $pdo;
        } catch (Throwable $e) {
            static::$app->make(\Psr\Log\LoggerInterface::class)
                ->error("Failed to create PDO connection for '{$name}': " . $e->getMessage());
            return false;
        }
    }

    protected static function ping(mixed $connection, string $name): bool
    {
        if (!$connection instanceof PDO) {
            return false;
        }

        $config = static::$configs[$name] ?? [];
        $heartbeat = $config['heartbeat'] ?? 60;

        if (isset(self::$lastUsedTimes[$connection]) && time() - self::$lastUsedTimes[$connection] < $heartbeat) {
            return true;
        }

        try {
            $connection->query('SELECT 1');
            self::$lastUsedTimes[$connection] = time();
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    protected static function isValid(mixed $connection): bool
    {
        if ($connection instanceof PDO) {
            return !$connection->inTransaction();
        }
        return false;
    }
}
