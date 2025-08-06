<?php

namespace Core\Queue;

use Core\Application;
use Core\Contracts\Queue\Queue;
use Core\Redis\RedisManager;
use InvalidArgumentException;

class QueueManager
{
    /**
     * The application instance.
     * @var Application
     */
    protected Application $app;

    /**
     * The array of resolved queue connections.
     * @var array
     */
    protected array $connections = [];

    /**
     * The array of registered queue connectors.
     * @var array
     */
    protected array $connectors = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get a queue connection instance.
     *
     * @param string|null $name
     * @return Queue
     */
    public function connection(?string $name = null): Queue
    {
        $name = $name ?: $this->getDefaultDriver();

        // If the connection has not been resolved yet, we will resolve it now.
        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->resolve($name);
        }

        return $this->connections[$name];
    }

    /**
     * Resolve a queue connection.
     *
     * @param string $name
     * @return Queue
     */
    protected function resolve(string $name): Queue
    {
        $config = $this->getConfig($name);

        if (isset($this->connectors[$config['driver']])) {
            return call_user_func($this->connectors[$config['driver']], $config);
        }

        throw new InvalidArgumentException("Unsupported queue driver [{$config['driver']}].");
    }

    /**
     * Add a queue connector.
     *
     * @param  string  $driver
     * @param  \Closure  $resolver
     * @return void
     */
    public function addConnector(string $driver, \Closure $resolver): void
    {
        $this->connectors[$driver] = $resolver;
    }

    /**
     * Get the name of the default queue connection.
     */
    public function getDefaultDriver(): string
    {
        return $this->app->make('config')->get('queue.default', 'sync');
    }

    /**
     * Get the queue connection configuration.
     *
     * @param  string  $name
     * @return array
     */
    protected function getConfig(string $name): array
    {
        $config = $this->app->make('config')->get("queue.connections.{$name}");

        if (is_null($config)) {
            throw new InvalidArgumentException("Queue connection [{$name}] is not configured.");
        }

        return $config;
    }

    /**
     * Dynamically pass methods to the default connection.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->connection()->{$method}(...$parameters);
    }
}
