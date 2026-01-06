<?php

namespace App\Providers;

use Core\Contracts\WebSocket\WebSocketManagerInterface;
use Core\Debug\DebugBroadcaster;
use Core\ServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Service Provider cho DebugBroadcaster - real-time debug broadcasting.
 */
class DebugBroadcasterServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(DebugBroadcaster::class, function ($app) {
            return new DebugBroadcaster(
                $app->make(WebSocketManagerInterface::class),
                $app->make(LoggerInterface::class),
            );
        });

        // Alias để dễ access
        $this->app->alias(DebugBroadcaster::class, 'debug.broadcaster');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Chỉ enable khi debug mode được bật
        if (!config('debug.enabled', false)) {
            return;
        }

        // Inject broadcaster vào Event Dispatcher để broadcast events
        $this->injectEventBroadcaster();
    }

    /**
     * Inject broadcaster vào event dispatcher.
     */
    protected function injectEventBroadcaster(): void
    {
        $events = $this->app->make('events');
        $broadcaster = $this->app->make(DebugBroadcaster::class);

        // Listen all events và broadcast chúng
        $events->listen('*', function ($eventName, $payload) use ($broadcaster) {
            if (!$broadcaster->isEnabled()) {
                return;
            }

            // Skip internal debug events
            if (str_starts_with($eventName, 'bault:') || str_starts_with($eventName, 'debug:')) {
                return;
            }

            $broadcaster->broadcastEvent($eventName, $payload);
        });
    }
}

