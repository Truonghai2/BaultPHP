<?php

namespace Core;

use Core\Support\Facades\Facade;

class AppKernel
{
    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;

        $this->app->instance(AppKernel::class, $this);

        Facade::setFacadeApplication($this->app);

        $this->app->singleton(\Core\Contracts\Http\Kernel::class, \App\Http\Kernel::class);

        $cachedProvidersPath = $this->app->getCachedProvidersPath();
        if (file_exists($cachedProvidersPath) && !config('app.debug')) {
            $providers = require $cachedProvidersPath;
        } else {
            $providerRepository = new ProviderRepository($this->app);
            $providers = $providerRepository->getAllProviders();
        }

        foreach ($providers as $provider) {
            $this->app->register($provider);
        }

        $this->app->boot();
    }

    public function getApplication(): Application
    {
        return $this->app;
    }

    /**
     * Get the list of all providers for caching purposes.
     * This is used by the `config:cache` command.
     * @return array
     */
    public function getProvidersForCaching(): array
    {
        $repository = new ProviderRepository($this->app);
        return $repository->getAllProviders();
    }
}
