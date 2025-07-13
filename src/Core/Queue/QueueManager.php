<?php

namespace Core\Queue;

use Core\Application;
use Core\Contracts\Queue\Job;
use Core\Queue\Drivers\Queue;
use Core\Queue\Drivers\RedisQueue;
use Core\Queue\Drivers\SyncQueue;
use InvalidArgumentException;

class QueueManager
{
    protected Application $app;
    protected array $connections = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function connection(string $name = null): Queue
    {
        $name = $name ?? $this->getDefaultDriver();

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->resolve($name);
        }

        return $this->connections[$name];
    }

    protected function resolve(string $name): Queue
    {
        $config = $this->getConfig($name);

        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

        if (!method_exists($this, $driverMethod)) {
            throw new InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
        }

        return $this->{$driverMethod}($config);
    }

    protected function createSyncDriver(array $config): Queue
    {
        return new SyncQueue($this->app);
    }

    protected function createRedisDriver(array $config): Queue
    {
        return new RedisQueue($this->app, $config);
    }

    protected function getConfig(string $name): array
    {
        return $this->app->make('config')->get("queue.connections.{$name}");
    }

    public function getDefaultDriver(): string
    {
        return $this->app->make('config')->get('queue.default');
    }

    public function push(Job $job, string $queue = null, string $connection = null): void
    {
        $this->connection($connection)->push($job, $queue);
    }
}