<?php 

namespace App\Providers;

use Core\Events\Dispatcher;
use Core\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected array $listen = [
        // Example:
        // \App\Events\UserRegistered::class => [
        //     \App\Listeners\SendWelcomeEmail::class,
        // ],
    ];

    /**
     * Register any events for your application.
     */
    public function register(): void
    {
        $this->app->singleton('events', function ($app) {
            return new Dispatcher($app);
        });
    }

    public function boot(): void
    {
        $events = $this->app->make('events');

        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                $events->listen($event, $listener);
            }
        }
    }
}