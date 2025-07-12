<?php

namespace App\Providers;

use Core\Logging\Logger;
use Core\Support\ServiceProvider;

class LoggingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Logger::class, function () {
            return new Logger();
        });
    }
}