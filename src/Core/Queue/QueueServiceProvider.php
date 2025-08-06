<?php

namespace Core\Queue;

use Core\Console\Commands\Queue\QueueFailedCommand;
use Core\Console\Commands\Queue\QueueFlushCommand;
use Core\Console\Commands\Queue\QueueForgetCommand;
use Core\Console\Commands\Queue\QueueRetryCommand;
use Core\Console\Commands\Queue\WorkCommand;
use Core\Redis\RedisManager;
use Core\Contracts\Queue\FailedJobProviderInterface;
use Core\Support\ServiceProvider;

/**
 * Class QueueServiceProvider
 *
 * Registers all queue related services into the container.
 */
class QueueServiceProvider extends ServiceProvider
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
        QueueRetryCommand::class,
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

        // Register the SwooleQueue driver specifically, as it's a core part of the async offering.
        $this->app->singleton(SwooleQueue::class, function ($app) {
            // This assumes the app is running within a Swoole server context.
            // An exception will be thrown if SwooleServer is not bound.
            return new SwooleQueue($app);
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
        $this->registerConnectors();
    }

    /**
     * Register the queue manager.
     */
    protected function registerManager(): void
    {
        $this->app->singleton('queue', fn($app) => new QueueManager($app));
        $this->app->alias('queue', QueueManager::class);
    }

    /**
     * Register the queue worker.
     */
    protected function registerWorker(): void
    {
        $this->app->bind(QueueWorker::class);
    }

    /**
     * Register the failed job services.
     */
    protected function registerFailedJobServices(): void
    {
        $this->app->singleton(
            FailedJobProviderInterface::class,
            DatabaseFailedJobProvider::class
        );
    }

    /**
     * Register the queue connectors.
     */
    protected function registerConnectors(): void
    {
        /** @var QueueManager $manager */
        $manager = $this->app->make('queue');

        $manager->addConnector('sync', fn() => new SyncQueue());

        $manager->addConnector('redis', function ($config) {
            return new RedisQueue(
                $this->app->make(RedisManager::class),
                $config['queue'] ?? 'default',
                $config['connection'] ?? null,
            );
        });

        $manager->addConnector('swoole', function () {
            // The connector's job is to return an instance of the Queue driver.
            // The SwooleQueue itself is registered as a singleton in the `register` method.
            return $this->app->make(SwooleQueue::class);
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
