<?php

namespace Core\Queue;

use Core\BaseServiceProvider;
use Core\Console\Commands\Queue\QueueFailedCommand;
use Core\Console\Commands\Queue\QueueFlushCommand;
use Core\Console\Commands\Queue\QueueForgetCommand;
use Core\Console\Commands\Queue\WorkCommand;
use Core\Contracts\Queue\FailedJobProviderInterface;
use Core\Queue\Drivers\RabbitMQQueue;
use Core\Queue\Drivers\RedisQueue as RedisQueueDriver;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Class QueueServiceProvider
 *
 * Registers all queue related services into the container.
 */
class QueueServiceProvider extends BaseServiceProvider
{
    /**
     * All of the console commands that should be registered.
     *
     * @var array
     */
    protected array $commands = [
        WorkCommand::class,
        QueueFailedCommand::class,
        QueueFlushCommand::class,
        QueueForgetCommand::class,
        \Core\Console\Commands\Queue\QueueRetryCommand::class,
    ];

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->registerManager();
        $this->registerWorker();
        $this->registerFailedJobServices();
        $this->registerConnectors(); // Moved from boot to register

        // Register the AMQP connection as a singleton to be reused.
        $this->app->singleton(AMQPStreamConnection::class, function ($app) {
            $config = $app->make('config')->get('queue.connections.rabbitmq');
            return new AMQPStreamConnection(
                $config['host'],
                $config['port'],
                $config['user'],
                $config['password'],
                $config['vhost'] ?? '/'
            );
        });
        $this->registerCommands();
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        // No longer needed here as it's moved to register()
    }

    /**
     * Register the queue manager.
     */
    protected function registerManager(): void
    {
        $this->app->singleton('queue', function (\Core\Application $app) {
            return new QueueManager($app);
        });
        $this->app->alias('queue', QueueManager::class);
    }

    /**
     * Register the queue worker.
     */
    protected function registerWorker(): void
    {
        $this->app->singleton(QueueWorker::class);
    }

    /**
     * Register the failed job services.
     */
    protected function registerFailedJobServices(): void
    {
        $this->app->singleton(
            FailedJobProviderInterface::class,
            DatabaseFailedJobProvider::class,
        );
    }

    /**
     * Register the queue connectors.
     */
    protected function registerConnectors(): void
    {
        /** @var QueueManager $manager */
        $manager = $this->app->make('queue');

        $manager->addConnector('sync', fn () => new SyncQueue());

        $manager->addConnector('redis', function ($config) {
            // This assumes you have a RedisManager or similar service to get a connection.
            // We will now make this registration "Swoole-aware".
            // The RedisQueueDriver is now responsible for resolving its own Redis connection
            // from the RedisManager, making the service provider cleaner.
            return new RedisQueueDriver($this->app, $config);
        });

        $manager->addConnector('rabbitmq', function ($config) {
            $connection = $this->app->make(AMQPStreamConnection::class);
            $defaultQueue = $config['queue'] ?? 'default';
            $exchangeOptions = $config['options']['exchange'] ?? [];

            return new RabbitMQQueue($connection, $defaultQueue, $exchangeOptions);
        });

        $manager->addConnector('swoole', function () {
            // The connector's job is to return an instance of the Queue driver.
            // The SwooleQueue itself is registered as a singleton in the `register` method.
            return new SwooleQueue($this->app);
        });
    }

    /**
     * Register the queue commands.
     */
    protected function registerCommands(): void
    {
        // Commands should only be registered when running in the console.
        // This check prevents unnecessary overhead for web requests.
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->app->tag($this->commands, 'console.command');
    }
}
