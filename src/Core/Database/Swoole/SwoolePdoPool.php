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
        
        if (!isset(static::$configs[$name])) {
            static::$app?->make(\Psr\Log\LoggerInterface::class)
                ->error("Pool config for '{$name}' not found. Available pools: " . implode(', ', array_keys(static::$configs)));
            return false;
        }
        
        $config = static::$configs[$name];

        $connectionDetails = $config['write'] ?? $config;

        $driver = $connectionDetails['driver'] ?? $config['driver'] ?? null;
        $host = $connectionDetails['host'] ?? $config['host'] ?? null;
        $port = $connectionDetails['port'] ?? $config['port'] ?? null;
        $database = $connectionDetails['database'] ?? $config['database'] ?? null;
        
        if (!$driver || !$host || !$port || !$database) {
            static::$app?->make(\Psr\Log\LoggerInterface::class)
                ->error("Invalid pool config for '{$name}'. Missing required fields: driver, host, port, or database.");
            return false;
        }

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
            $pdo = new PDO(
                $dsn,
                $connectionDetails['username'] ?? $config['username'],
                $connectionDetails['password'] ?? $config['password'],
                $options,
            );
            self::$lastUsedTimes[$pdo] = time();
            return $pdo;
        } catch (Throwable $e) {
            static::$app->make(\Psr\Log\LoggerInterface::class)
                ->error("Failed to create PDO connection for '{$name}': " . $e->getMessage());
            return false;
        }
    }

    protected static function ping(mixed $rawConnection, string $name): bool
    {
        if (!$rawConnection instanceof PDO) {
            return false;
        }

        $config = static::$configs[$name] ?? [];
        $heartbeat = $config['heartbeat'] ?? 60;

        if (isset(self::$lastUsedTimes[$rawConnection]) && time() - self::$lastUsedTimes[$rawConnection] < $heartbeat) {
            return true;
        }

        try {
            $rawConnection->query('SELECT 1');
            self::$lastUsedTimes[$rawConnection] = time();
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    protected static function isValid(mixed $rawConnection): bool
    {
        if ($rawConnection instanceof PDO) {
            return !$rawConnection->inTransaction();
        }
        return false;
    }

    /**
     * Lấy thông tin trạng thái của một pool cụ thể.
     *
     * @param string $name Tên của pool.
     * @return array|null
     */
    public static function stats(string $name): ?array
    {
        if (!isset(static::$pools[$name])) {
            return null;
        }

        return [
            'pool_size' => static::$pools[$name]->capacity,
            'connections_in_use' => static::$pools[$name]->capacity - static::$pools[$name]->length(),
            'connections_idle' => static::$pools[$name]->length(),
        ];
    }

    /** @return array<string, array> */
    public static function getAllStats(): array
    {
        $stats = [];
        foreach (array_keys(static::$pools) as $name) {
            $stats[$name] = static::stats($name);
        }
        return $stats;
    }
}
