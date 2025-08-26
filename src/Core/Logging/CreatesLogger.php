<?php

namespace Core\Logging;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

trait CreatesLogger
{
    /**
     * Create an instance of the single file log driver.
     *
     * @param  array  $config
     * @return \Psr\Log\LoggerInterface
     */
    protected function createSingleDriver(array $config): Logger
    {
        return new Logger($this->parseChannel($config), [
            $this->prepareHandler(
                new StreamHandler(
                    $config['path'],
                    $this->level($config),
                    $config['bubble'] ?? true,
                    $config['permission'] ?? null,
                    $config['locking'] ?? false,
                ),
                $config,
            ),
        ]);
    }

    /**
     * Create an instance of the daily file log driver.
     *
     * @param  array  $config
     * @return \Psr\Log\LoggerInterface
     */
    protected function createDailyDriver(array $config): Logger
    {
        return new Logger($this->parseChannel($config), [
            $this->prepareHandler(
                new RotatingFileHandler(
                    $config['path'],
                    $config['days'] ?? 7,
                    $this->level($config),
                    $config['bubble'] ?? true,
                    $config['permission'] ?? null,
                    $config['locking'] ?? false,
                ),
                $config,
            ),
        ]);
    }

    /**
     * Create an instance of the stack log driver.
     *
     * @param  array  $config
     * @return \Psr\Log\LoggerInterface
     */
    protected function createStackDriver(array $config): Logger
    {
        $handlers = [];
        foreach ($config['channels'] as $channel) {
            $handlers = array_merge($handlers, $this->channel($channel)->getHandlers());
        }

        return new Logger($this->parseChannel($config), $handlers);
    }

    /**
     * Prepare the handlers for usage by Monolog.
     *
     * @param  \Monolog\Handler\HandlerInterface  $handler
     * @param  array  $config
     * @return \Monolog\Handler\HandlerInterface
     */
    protected function prepareHandler(\Monolog\Handler\HandlerInterface $handler, array $config = []): \Monolog\Handler\HandlerInterface
    {
        $handler->setFormatter($this->formatter($config));

        return $handler;
    }

    /**
     * Get a Monolog formatter instance.
     *
     * @param  array  $config
     * @return \Monolog\Formatter\FormatterInterface
     */
    protected function formatter(array $config): \Monolog\Formatter\FormatterInterface
    {
        return new LineFormatter(
            '[%datetime%] %channel%.%level_name%: %message% %context% %extra%' . PHP_EOL,
            'Y-m-d H:i:s',
            true,
            true,
        );
    }

    /**
     * Parse the log level from the configuration.
     *
     * @param  array  $config
     * @return \Monolog\Level
     */
    protected function level(array $config): \Monolog\Level
    {
        return Logger::toMonologLevel($config['level'] ?? 'debug');
    }
}
