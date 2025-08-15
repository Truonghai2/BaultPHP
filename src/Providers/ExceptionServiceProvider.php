<?php

namespace App\Providers;

use App\Exceptions\Handler;
use Core\Contracts\Exceptions\Handler as HandlerContract;
use Core\Support\ServiceProvider;

class ExceptionServiceProvider extends ServiceProvider
{
    /**
     * Register the exception services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(HandlerContract::class, Handler::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        // This ensures that PHP errors (like Warnings) are converted into exceptions,
        // which can then be caught and rendered by Whoops in debug mode.
        $this->app->make(HandlerContract::class)->bootstrap();
    }
}
