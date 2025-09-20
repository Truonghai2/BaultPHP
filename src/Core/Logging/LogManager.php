<?php

namespace Core\Logging;

use Core\Application;
use Core\Contracts\Task\TaskDispatcher;
use InvalidArgumentException;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Sentry\Monolog\Handler as SentryHandler;
use Sentry\SentrySdk;

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

    /**
     * Create an instance of any Monolog driver.
     *
     * @param  array  $config
     * @return \Psr\Log\LoggerInterface
     */
    protected function createMonologDriver(array $config): LoggerInterface
    {
        if (!isset($config['handler'])) {
            throw new InvalidArgumentException('Log channel ' . ($config['channel'] ?? 'unknown') . ' with monolog driver must have a handler defined.');
        }

        $handlerClass = $config['handler'];
        $handlerArgs = $config['with'] ?? [];

        if (is_string($handlerClass) && $this->app->bound($handlerClass)) {
            $handler = $this->app->make($handlerClass);
        } else {
            $handler = new $handlerClass(...array_values($handlerArgs));
        }

        return new Logger(
            $this->parseChannel($config),
            [$this->prepareHandler($handler, $config)]
        );
    }

    protected function resolve(string $name): LoggerInterface
    {
        $config = $this->configurationFor($name);

        if (is_null($config)) {
            throw new InvalidArgumentException("Log channel [{$name}] is not defined.");
        }

        $logger = null;

        if (isset($this->customCreators[$config['driver']])) {
            $logger = $this->callCustomCreator($config);
        } else {
            $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

            if (method_exists($this, $driverMethod)) {
                $logger = $this->{$driverMethod}($config);
            } else {
                throw new InvalidArgumentException("Driver [{$config['driver']}] for log channel [{$name}] is not supported.");
            }
        }

        foreach ($this->processors as $processor) {
            $logger->pushProcessor($processor);
        }

        return $logger;
    }

    protected function createSentryDriver(array $config): LoggerInterface
    {
        $handler = new SentryHandler(SentrySdk::getCurrentHub(), $this->level($config));

        return new Logger(
            $this->parseChannel($config),
            [$handler],
        );
    }

    protected function createAsyncDriver(array $config): LoggerInterface
    {
        // Kiểm tra xem có đang chạy trong môi trường Swoole coroutine hay không
        $isSwooleEnv = extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0;

        // Nếu không phải môi trường Swoole, hoặc TaskDispatcher chưa được bind,
        // thì quay về dùng 'single' driver như một phương án an toàn.
        if (! $isSwooleEnv || ! $this->app->bound(TaskDispatcher::class)) {
            $this->app->make(LoggerInterface::class)->warning('Async log driver is not available. Falling back to single driver. Ensure you are in a Swoole environment and TaskDispatcher is bound.');
            $singleConfig = $this->app['config']->get('logging.channels.single');
            $singleConfig['level'] = $config['level'] ?? $singleConfig['level'];
            return $this->createSingleDriver($singleConfig);
        }

        // Sử dụng handler mới để gửi log đến Task Worker
        $handler = new SwooleTaskHandler(
            $this->app->make(TaskDispatcher::class),
            $this->level($config),
            $config['bubble'] ?? true,
        );

        return new Logger(
            $this->parseChannel($config),
            [$this->prepareHandler($handler, $config)],
        );
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

        foreach ($this->channels as $channel) {
            $channel->pushProcessor($processor);
        }

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
        $this->channel()->log($level, $message, context);
    }

    public function __call(string $method, array $parameters)
    {
        return $this->channel()->{$method}(...$parameters);
    }
}
