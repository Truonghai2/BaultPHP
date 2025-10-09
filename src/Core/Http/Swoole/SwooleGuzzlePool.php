<?php

namespace Core\Http\Swoole;

use Core\Database\Swoole\BaseSwoolePool;
use GuzzleHttp\ClientInterface;

/**
 * Manages a connection pool of Guzzle HTTP clients.
 *
 * This class provides a static interface to get and put Guzzle clients
 * from a Swoole Coroutine Channel, ensuring efficient reuse of clients.
 *
 * @method static ClientInterface get(string $name = 'default')
 * @method static void put(ClientInterface $connection, string $name = 'default')
 */
class SwooleGuzzlePool extends BaseSwoolePool
{
    /**
     * The connector used to create new connections.
     *
     * @var SwooleGuzzleConnector
     */
    protected $connector;

    /**
     * @param string $name
     * @return mixed
     */
    protected static function createConnection(string $name): mixed
    {
        $config = static::$configs[$name] ?? [];
        $connector = new SwooleGuzzleConnector();
        return $connector->connect($config);
    }

    /**
     * @param mixed $rawConnection
     * @param string $name
     * @return bool
     */
    protected static function ping(mixed $rawConnection, string $name): bool
    {
        return true;
    }
}
