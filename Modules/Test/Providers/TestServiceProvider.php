<?php

namespace Modules\Test\Providers;

use Core\Support\ServiceProvider;

class TestServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register bindings for this module, e.g., repositories.
        //
        // $this->app->bind(
        //     \Modules\Test\Domain\Repositories\PostRepositoryInterface::class,
        //     \Modules\Test\Infrastructure\Repositories\EloquentPostRepository::class
        // );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Booting services, maybe loading routes or event listeners.
        // Attribute-based routing is auto-discovered, so no need to load routes here.
        // Event listeners are also auto-discovered from the module's events.php file.
    }
}
