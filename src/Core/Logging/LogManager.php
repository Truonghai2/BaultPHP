<?php

namespace Core\Logging;

use Core\Application;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class LogManager implements LoggerInterface
{
    use CreatesLogger;

    protected Application $app;
    protected array $channels = [];
    protected array $customCreators = [];
    protected array $processors = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function channel(?string $channel = null): LoggerInterface
    {
        return $this->get($channel ?? $this->getDefaultDriver());
    }

    public function driver(?string $driver = null): LoggerInterface
    {
        return $this->channel($driver);
    }

    protected function get(string $name): LoggerInterface
    {
        return $this->channels[$name] ?? ($this->channels[$name] = $this->resolve($name));
    }

    protected function resolve(string $name): LoggerInterface
    {
        $config = $this->configurationFor($name);

        if (is_null($config)) {
            throw new InvalidArgumentException("Log channel [{$name}] is not defined.");
        }

        if (isset($this->customCreators[$config['driver']])) {
            return $this->callCustomCreator($config);
        }

        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

        if (method_exists($this, $driverMethod)) {
            return $this->{$driverMethod}($config);
        }

        throw new InvalidArgumentException("Driver [{$config['driver']}] for log channel [{$name}] is not supported.");
    }

    protected function callCustomCreator(array $config): LoggerInterface
    {
        return $this->customCreators[$config['driver']]($this->app, $config);
    }

    protected function configurationFor(string $name): ?array
    {
        return $this->app->make('config')->get("logging.channels.{$name}");
    }

    public function getDefaultDriver(): string
    {
        return $this->app->make('config')->get('logging.default', 'stack');
    }

    public function setDefaultDriver(string $name): void
    {
        $this->app->make('config')->set('logging.default', $name);
    }

    public function extend(string $driver, \Closure $callback): self
    {
        $this->customCreators[$driver] = $callback;
        return $this;
    }

    protected function parseChannel(array $config): string
    {
        return $config['channel'] ?? $this->app->make('config')->get('app.env');
    }

    public function pushProcessor($processor): self
    {
        $this->processors[] = $processor;
        return $this;
    }

    public function emergency($message, array $context = []): void
    {
        $this->channel()->emergency($message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->channel()->alert($message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->channel()->critical($message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->channel()->error($message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->channel()->warning($message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->channel()->notice($message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->channel()->info($message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->channel()->debug($message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        $this->channel()->log($level, $message, $context);
    }

    public function __call(string $method, array $parameters)
    {
        return $this->channel()->{$method}(...$parameters);
    }
}
