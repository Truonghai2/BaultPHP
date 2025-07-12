<?php

namespace App\Providers;

use Core\Support\ServiceProvider;
use Core\Console\DbSeedCommand;
use Core\Console\ConfigCacheCommand;
use Core\Console\KeyGenerateCommand;
use Core\Console\MakeAllCommand;
use Core\Console\MakeEventCommand;
use Core\Console\MakeMigrationCommand;
use Core\Console\MakeListenerCommand;
use Core\Console\MakeModuleCommand;
use Core\Console\MakeUseCaseCommand;
use Core\Console\MigrateModulesCommand;
use Core\Console\RouteCacheCommand;
use Core\Console\ServeCommand;

class ConsoleServiceProvider extends ServiceProvider
{
    protected array $commands = [
        // Framework & Server
        RouteCacheCommand::class,
        ConfigCacheCommand::class,
        KeyGenerateCommand::class,
        ServeCommand::class,
        MigrateModulesCommand::class,
        DbSeedCommand::class,

        // Generators
        MakeAllCommand::class, // make:command
        MakeEventCommand::class,
        MakeListenerCommand::class,
        MakeMigrationCommand::class,
        MakeModuleCommand::class,
        MakeUseCaseCommand::class,
    ];

    public function register(): void
    {
        foreach ($this->commands as $commandClass) {
            // Bằng cách bind lớp command vào chính nó dưới dạng singleton,
            // chúng ta để cho dependency injection container tự xử lý việc khởi tạo.
            // Nó sẽ tự động giải quyết các dependency trong constructor nếu có.
            $this->app->singleton($commandClass);
        }
    }
}