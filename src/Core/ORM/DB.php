<?php

namespace Core\ORM;

use Core\Application;

/**
 * @method static QueryBuilder table(string $table)
 * @method static \PDO|\Swoole\Database\PDOProxy connection(string $name = null, string $type = 'write')
 * @method static void beginTransaction(string $name = null)
 * @method static void commit(string $name = null)
 * @method static void rollBack(string $name = null)
 * @method static bool inTransaction(string $name = null)
 * @see \Core\ORM\Connection
 */
class DB
{
    /**
     * The connection instance.
     *
     * @var \Core\ORM\Connection|null
     */
    protected static ?Connection $connection = null;

    /**
     * Get the connection instance.
     *
     * @return \Core\ORM\Connection
     */
    protected static function getConnectionInstance(): Connection
    {
        if (static::$connection === null) {
            static::$connection = app(Connection::class);
        }
        return static::$connection;
    }

    public static function __callStatic(string $method, array $parameters): mixed
    {
        return static::getConnectionInstance()->{$method}(...$parameters);
    }
}