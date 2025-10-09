<?php

namespace Core\Queue;

use Core\BaseServiceProvider;
use Core\Console\Commands\Queue\QueueFailedCommand;
use Core\Console\Commands\Queue\QueueFlushCommand;
use Core\Console\Commands\Queue\QueueForgetCommand;
use Core\Console\Commands\Queue\WorkCommand;
use Core\Contracts\Queue\FailedJobProviderInterface;
use Core\Contracts\Support\DeferrableProvider;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Class QueueServiceProvider
 *
 * Registers all queue related services into the container.
 */
class QueueServiceProvider extends BaseServiceProvider implements DeferrableProvider
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
                $config['vhost'] ?? '/',
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
    }

    /**
     * Register the queue commands.
     */
    protected function registerCommands(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->app->tag($this->commands, 'console.command');
    }

    /**
     * Get the services provided by the provider.
     *
     * This provider will only be loaded when one of these services is requested from the container.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            QueueManager::class,
            'queue',
            \Core\Contracts\Queue\Queue::class,
            QueueWorker::class,
            AMQPStreamConnection::class,
            FailedJobProviderInterface::class,
        ];
    }
}
