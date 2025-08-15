<?php

namespace Core\Queue;

use Closure;
use Core\Application;
use Core\Contracts\Queue\Queue as QueueContract;
use InvalidArgumentException;

class QueueManager
{
    /**
     * The application instance.
     *
     * @var \Core\Application
     */
    protected Application $app;

    /**
     * The array of resolved queue connections.
     *
     * @var array
     */
    protected array $connections = [];

    /**
     * The array of queue connectors.
     *
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
     * @param  string|null  $name
     * @return \Core\Contracts\Queue\Queue
     */
    public function connection(?string $name = null): QueueContract
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
     * @param  string  $name
     * @return \Core\Contracts\Queue\Queue
     */
    protected function resolve(string $name): QueueContract
    {
        $config = $this->getConfig($name);

        if (!isset($this->connectors[$config['driver']])) {
            throw new InvalidArgumentException("No connector for [{$config['driver']}].");
        }

        return $this->connectors[$config['driver']]($config);
    }

    /**
     * Add a queue connector.
     *
     * @param  string  $driver
     * @param  \Closure  $resolver
     * @return void
     */
    public function addConnector(string $driver, Closure $resolver): void
    {
        $this->connectors[$driver] = $resolver;
    }

    /**
     * Get the queue connection configuration.
     *
     * @param  string  $name
     * @return array
     */
    protected function getConfig(string $name): array
    {
        return $this->app->make('config')->get("queue.connections.{$name}");
    }

    public function getDefaultDriver(): string
    {
        return $this->app->make('config')->get('queue.default');
    }

    /**
     * Dynamically pass calls to the default connection.
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
