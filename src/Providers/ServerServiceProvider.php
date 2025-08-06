<?php

namespace App\Providers;

use Core\Console\Commands\Swoole\StartSwooleCommand;
use Core\Server\SwooleServer;
use Core\Support\ServiceProvider;

class ServerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Đăng ký SwooleServer như một singleton
        $this->app->singleton(SwooleServer::class, function ($app) {
            return new SwooleServer($app);
        });

        $this->registerCommands();
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->app->singleton(StartSwooleCommand::class);
            // Tag lệnh để Console Application có thể tìm thấy
            $this->app->tag(StartSwooleCommand::class, 'console.command');
        }
    }
}

