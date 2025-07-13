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
use Core\Console\MakeRuleCommand;
use Core\Console\ModuleManageCommand;
use Core\Console\MigrateModulesCommand;
use Core\Console\MakeJobCommand;
use Core\Console\QueueFlushCommand;
use Core\Console\QueueForgetCommand;
use Core\Console\QueueRetryCommand;
use Core\Console\QueueFailedCommand;
use Core\Console\QueueWorkCommand;
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
        ModuleManageCommand::class,
        QueueWorkCommand::class,
        QueueFailedCommand::class,
        QueueForgetCommand::class,
        QueueRetryCommand::class,
        QueueFlushCommand::class,

        // Generators
        MakeAllCommand::class, // make:command
        MakeEventCommand::class,
        MakeListenerCommand::class,
        MakeMigrationCommand::class,
        MakeModuleCommand::class,
        \Core\Console\MakeModelCommand::class,
        MakeUseCaseCommand::class,
        MakeJobCommand::class,
        MakeRuleCommand::class,
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