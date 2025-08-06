<?php

namespace Core\Database\Swoole;

use PDO;
use Swoole\Database\PDOConfig;
use Swoole\Database\PDOPool;
use RuntimeException;

/**
 * Class SwoolePdoPool
 * A static wrapper for the Swoole PDOPool to make it globally accessible.
 */
class SwoolePdoPool
{
    protected static ?PDOPool $pool = null;

    /**
     * Initialize the connection pool.
     */
    public static function init(array $dbConfig, int $poolSize): void
    {
        if (self::$pool) {
            return;
        }

        $pdoConfig = (new PDOConfig())
            ->withDriver($dbConfig['driver'])
            ->withHost($dbConfig['host'])
            ->withPort($dbConfig['port'])
            ->withDbName($dbConfig['database'])
            ->withUsername($dbConfig['username'])
            ->withPassword($dbConfig['password'])
            ->withCharset($dbConfig['charset']);

        self::$pool = new PDOPool($pdoConfig, $poolSize);
    }

    public static function get(): PDO
    {
        if (!self::$pool) {
            throw new RuntimeException('Swoole PDO connection pool has not been initialized.');
        }
        return self::$pool->get();
    }

    public static function put(?PDO $connection): void
    {
        if (self::$pool && $connection) {
            self::$pool->put($connection);
        }
    }

    public static function close(): void
    {
        if (self::$pool) {
            self::$pool->close();
            self::$pool = null;
        }
    }
}

