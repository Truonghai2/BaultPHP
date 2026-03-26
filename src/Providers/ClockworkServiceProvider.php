<?php

namespace App\Providers;

use Core\BaseServiceProvider;
use Core\Contracts\StatefulService;

/**
 * Clockwork Service Provider for BaultFrame
 * 
 * Integrates Clockwork profiler with Swoole-based framework.
 * Key considerations:
 * - Clockwork instance must be reset per-request in Swoole
 * - Uses FileStorage for simplicity (stores data in storage/clockwork)
 * - Designed to work with ClockworkMiddleware
 */
class ClockworkServiceProvider extends BaseServiceProvider implements StatefulService
{

    public function register(): void
    {
        if (!class_exists(\Clockwork\Clockwork::class)) {
            // Bind null so middleware can receive null instead of failing
            $this->app->instance(\Clockwork\Clockwork::class, null);
            return;
        }

        // Chỉ đăng ký Clockwork khi debug hoặc bật tường minh (tránh tốn tài nguyên trên production/Swoole)
        if (!filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN) && !filter_var(env('CLOCKWORK_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->app->instance(\Clockwork\Clockwork::class, null);
            return;
        }

        $this->app->singleton(\Clockwork\Clockwork::class, function ($app) {
            $storagePath = storage_path('clockwork');
            
            // Ensure storage directory exists
            if (!is_dir($storagePath)) {
                mkdir($storagePath, 0755, true);
            }

            $storage = new \Clockwork\Storage\FileStorage($storagePath);
            $clockwork = new \Clockwork\Clockwork();
            
            $clockwork->setStorage($storage);
            
            // Add PHP data source (memory, execution time, etc.)
            $clockwork->addDataSource(new \Clockwork\DataSource\PhpDataSource());
            
            return $clockwork;
        });

        // Tag Clockwork as a StatefulService for automatic state reset in Swoole
        $this->app->tag(\Clockwork\Clockwork::class, \Core\Contracts\StatefulService::class);
    }

    public function boot(): void
    {
        // Boot logic if needed in the future
    }

    /**
     * Reset Clockwork state for the next request
     * This is critical in Swoole to prevent data leakage between requests
     */
    public function resetState(): void
    {
        if ($this->app->bound(\Clockwork\Clockwork::class)) {
            $clockwork = $this->app->make(\Clockwork\Clockwork::class);
            if ($clockwork !== null) {
                $clockwork->reset();
            }
        }
    }
}
