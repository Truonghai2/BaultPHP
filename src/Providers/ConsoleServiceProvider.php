<?php

namespace App\Providers;

use Core\CLI\ConsoleKernel;
use Core\Console\Commands\Cache\ConfigCacheCommand;
use Core\Console\Commands\Cache\ConfigClearCommand;
use Core\Console\Commands\Database\MigrateModulesCommand;
use Core\Console\Commands\OptimizeCommand;
use Core\Console\Commands\OptimizeCompileCommand;
use Core\Console\Commands\View\ViewClearCommand;
use Core\Contracts\Console\Kernel as KernelContract;
use Core\Support\ServiceProvider;

/**
 * This provider is responsible for bootstrapping the console application.
 * It binds the console kernel into the service container.
 */
class ConsoleServiceProvider extends ServiceProvider
{
    /**
     * Register the console kernel implementation.
     */
    public function register(): void
    {
        $this->app->singleton(KernelContract::class, function ($app) {
            return new ConsoleKernel($app);
        });

        $this->registerCommands();
    }

    /**
     * Đăng ký các lệnh console cốt lõi.
     *
     * Chúng ta sử dụng pattern `singleton` và `tag` để cho phép Kernel của console
     * tự động phát hiện và nạp các lệnh này.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $commands = [
                ViewClearCommand::class,
                ConfigCacheCommand::class,
                ConfigClearCommand::class,
                OptimizeCompileCommand::class,
                OptimizeCommand::class,
                MigrateModulesCommand::class,
            ];
            foreach ($commands as $command) {
                $this->app->singleton($command);
                $this->app->tag($command, 'console.command');
            }
        }
    }
}
