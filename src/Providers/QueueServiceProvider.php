<?php

namespace App\Providers;

use Core\Queue\QueueManager;
use Core\Support\ServiceProvider;

class QueueServiceProvider extends ServiceProvider
{
    /**
     * Register the queue services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton('queue', function ($app) {
            return new QueueManager($app);
        });
    }
}