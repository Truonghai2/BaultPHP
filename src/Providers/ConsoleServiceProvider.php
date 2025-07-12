<?php

namespace App\Providers;

use Core\Support\ServiceProvider;
use Core\Console\RouteCacheCommand;
use Core\Console\ConfigCacheCommand;
use Core\Console\MakeEventCommand;
use Core\Console\MakeListenerCommand;

class ConsoleServiceProvider extends ServiceProvider
{
    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected array $commands = [
        RouteCacheCommand::class,
        ConfigCacheCommand::class,
        MakeEventCommand::class,
        MakeListenerCommand::class,
    ];

    public function register(): void
    {
        foreach ($this->commands as $commandClass) {
            $this->app->singleton($commandClass, function ($app) use ($commandClass) {
                return new $commandClass($app);
            });
        }
    }
}