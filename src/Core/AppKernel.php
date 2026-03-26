<?php

namespace Core;

use Core\Support\Facades\Facade;
use Core\Support\Benchmark;

class AppKernel
{
    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;

        $this->bootstrap();
    }

    /**
     * Bootstrap the application kernel.
     */
    protected function bootstrap(): void
    {
        $this->app->instance(AppKernel::class, $this);

        Facade::setFacadeApplication($this->app);

        $this->app->singleton(\Core\Contracts\Http\Kernel::class, \App\Http\Kernel::class);

        $this->app->singleton(\Core\Contracts\Console\Kernel::class, \Core\CLI\ConsoleKernel::class);

        $metricsEnabled = (bool) ($_ENV['APP_PROFILE_BOOT'] ?? false);
        if ($metricsEnabled) {
            Benchmark::start('app_kernel_boot');
        }

        $providers = $this->resolveProviders();
        $this->registerProviders($providers);
        $this->bootApplication($metricsEnabled);
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

    /**
     * Resolve provider list from cache or repository.
     *
     * @return string[]
     */
    protected function resolveProviders(): array
    {
        $cachedProvidersPath = $this->app->getCachedProvidersPath();

        if ($this->shouldUseCachedProviders($cachedProvidersPath)) {
            $providers = require $cachedProvidersPath;
            if (is_array($providers)) {
                return $this->filterValidProviders($providers);
            }
        }

        $providerRepository = new ProviderRepository($this->app);
        return $this->filterValidProviders($providerRepository->getAllProviders());
    }

    /**
     * Determine if cached providers should be used.
     */
    protected function shouldUseCachedProviders(string $cachedProvidersPath): bool
    {
        if (!file_exists($cachedProvidersPath)) {
            return false;
        }

        if (!config('app.debug')) {
            return true;
        }

        return (bool) config('app.cache_providers', false);
    }

    /**
     * Register providers safely.
     *
     * @param string[] $providers
     */
    protected function registerProviders(array $providers): void
    {
        foreach ($providers as $provider) {
            if (!is_string($provider)) {
                continue;
            }

            if (!class_exists($provider)) {
                error_log('[AppKernel] Skipping missing provider: ' . $provider);
                continue;
            }

            $this->app->register($provider);
        }
    }

    /**
     * Boot the application with optional timing.
     */
    protected function bootApplication(bool $metricsEnabled): void
    {
        $this->app->boot();

        if ($metricsEnabled) {
            $stats = Benchmark::stop('app_kernel_boot');
            error_log(sprintf(
                '[AppKernel] Boot time: %.2fms, memory: %s, peak: %s',
                $stats['time'],
                Benchmark::formatBytes($stats['memory']),
                Benchmark::formatBytes($stats['memory_peak'])
            ));
        }
    }

    /**
     * Filter provider list to existing classes.
     *
     * @param string[] $providers
     * @return string[]
     */
    protected function filterValidProviders(array $providers): array
    {
        $valid = [];
        foreach ($providers as $provider) {
            if (is_string($provider) && class_exists($provider)) {
                $valid[] = $provider;
            }
        }
        return array_values(array_unique($valid));
    }
}
