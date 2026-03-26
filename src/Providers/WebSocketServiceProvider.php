<?php

namespace App\Providers;

use Core\Contracts\WebSocket\WebSocketManagerInterface;
use Core\Realtime\SSEStream;
use Core\Realtime\WebRTCManager;
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

        // Register SSE Stream
        $this->app->singleton(SSEStream::class, function ($app) {
            $config = config('realtime-streaming.sse', []);
            return new SSEStream($config);
        });

        // Register WebRTC Manager
        $this->app->singleton(WebRTCManager::class, function ($app) {
            $config = config('realtime-streaming.webrtc', []);
            return new WebRTCManager($config);
        });
    }
}
