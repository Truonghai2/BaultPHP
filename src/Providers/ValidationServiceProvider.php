<?php

namespace App\Providers;

use Core\Contracts\Support\DeferrableProvider;
use Core\Support\ServiceProvider;
use Core\Validation\Factory as ValidationFactory;

class ValidationServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the validation services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(ValidationFactory::class, function ($app) {
            return new ValidationFactory($app);
        });

        $this->app->alias(ValidationFactory::class, 'validator');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [ValidationFactory::class, 'validator'];
    }
}
