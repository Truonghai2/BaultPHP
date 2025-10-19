<?php

namespace App\Providers;

use Core\Contracts\WebSocket\WebSocketManagerInterface;
use Core\Support\ServiceProvider;
use Core\WebSocket\WebSocketManager;

class WebSocketServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WebSocketManagerInterface::class, WebSocketManager::class);
        $this->app->singleton(WebSocketManager::class);
    }
}
