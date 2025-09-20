<?php

namespace App\Providers;

use Core\Contracts\StatefulService;
use Core\Auth\AuthManager;
use Core\Foundation\StateResetter;
use Core\Support\ServiceProvider;
use Core\View\ViewFactory;

class StatefulServiceProvider extends ServiceProvider
{
    /**
     * List of stateful services that are not tagged in their own providers.
     * This acts as a fallback. Ideally, each service provider should be
     * responsible for tagging its own stateful services.
     *
     * @var string[]
     */
    protected array $statefulServices = [
        AuthManager::class,
        ViewFactory::class,
        // DebugManager is already tagged in DebugServiceProvider.
        // Other services like SessionManager and CookieManager are tagged in their respective providers.
    ];

    public function register(): void
    {
        foreach ($this->statefulServices as $service) {
            $this->app->tag($service, StatefulService::class);
        }

        $this->app->singleton(StateResetter::class, function ($app) {
            return new StateResetter(
                $app->getTagged(StatefulService::class)
            );
        });

        $this->app->alias(StateResetter::class, 'state.resetter');
    }
}
