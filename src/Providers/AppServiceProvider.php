<?php

namespace App\Providers;

use App\Services\SanitizerService;
use Core\Auth\TokenIssuerService;
use Core\Console\Commands\MakeProviderCommand;
use Core\Contracts\Http\Kernel as KernelContract;
use Core\Contracts\Queue\Dispatcher;
use Core\Contracts\StatefulService;
use Core\Http\FormRequest;
use Core\Queue\QueueDispatcher;
use Core\Queue\QueueManager;
use Core\Redis\FiberRedisManager;
use Core\Services\HealthCheckService;
use Core\Support\ServiceProvider;
use Core\WebSocket\WebSocketManager;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        date_default_timezone_set($this->app['config']->get('app.timezone', 'UTC'));

        $this->app->singleton(KernelContract::class, \App\Http\Kernel::class);
        $this->app->tag(KernelContract::class, StatefulService::class);

        $this->app->singleton(TokenIssuerService::class, function ($app) {
            $key = config('app.key');
            return new TokenIssuerService($key);
        });

        $this->app->singleton(FiberRedisManager::class);

        $this->app->singleton(HealthCheckService::class);

        $this->app->singleton(WebSocketManager::class);

        $this->app->singleton(QueueManager::class);
        
        $this->app->singleton(Dispatcher::class, QueueDispatcher::class);

        $this->configureFormRequestValidation();

        $this->app->singleton(SanitizerService::class);

        $this->registerCommands();
    }

    protected function configureFormRequestValidation(): void
    {
        $this->app->afterResolving(FormRequest::class, function (FormRequest $request) {
            $request->validateResolved();
        });
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->app->singleton(MakeProviderCommand::class);
            $this->app->tag(MakeProviderCommand::class, 'console.command');
        }
    }
}
