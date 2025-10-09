<?php

namespace Core\Foundation;

use Core\Application;

class ProviderRepository
{
    public function __construct(protected Application $app)
    {
    }

    /**
     * Load and register service providers.
     * If a cache file exists, it will be used. Otherwise, providers will be discovered and registered on the fly.
     *
     * @param string $cachePath The path to the cached providers file.
     */
    public function load(string $cachePath): void
    {
        if (file_exists($cachePath)) {
            $this->loadFromCache($cachePath);
            return;
        }

        $this->discoverAndRegister();
    }

    /**
     * Load and register providers directly from the cache file.
     *
     * @param string $path The path to the cached providers file.
     */
    public function loadFromCache(string $path): void
    {
        $providers = require $path;
        foreach ($providers as $provider) {
            $this->app->register($provider);
        }
    }

    /**
     * Discover all providers from config and modules, then register them with the application.
     */
    public function discoverAndRegister(): void
    {
        $providers = $this->discover();
        foreach ($providers as $provider) {
            $this->app->register($provider);
        }
    }

    /**
     * Discover all service providers from the core configuration and enabled modules.
     * This is the single source of truth for provider discovery.
     *
     * @return array<int, class-string>
     */
    public function discover(): array
    {
        // We can't use the config service here as it's not registered yet.
        // We must load the app config file directly.
        $coreProviders = $this->getProvidersFromAppConfig();

        $moduleProviders = [];
        $moduleJsonPaths = glob($this->app->basePath('Modules/*/module.json'));

        if ($moduleJsonPaths === false) {
            $moduleJsonPaths = [];
        }

        foreach ($moduleJsonPaths as $path) {
            $data = json_decode(file_get_contents($path), true);
            if (!empty($data['name']) && !empty($data['enabled']) && $data['enabled'] === true) {
                foreach ($data['providers'] ?? [] as $providerClass) {
                    if (class_exists($providerClass)) {
                        $moduleProviders[] = $providerClass;
                    }
                }
            }
        }

        $frameworkProviders = [];
        $otherProviders = [];

        // Get all unique providers from core and modules.
        $allProviders = array_unique(array_merge($coreProviders, $moduleProviders));

        // Separate framework providers from application providers to control load order.
        foreach ($allProviders as $provider) {
            if (str_starts_with($provider, 'Core\\')) {
                $frameworkProviders[] = $provider;
            } else {
                $otherProviders[] = $provider;
            }
        }

        sort($frameworkProviders);
        sort($otherProviders);

        // Ensure ConfigServiceProvider is always first.
        $configProvider = \App\Providers\ConfigServiceProvider::class;
        $otherProviders = array_filter($otherProviders, fn ($p) => $p !== $configProvider); // Remove it from its current position

        // Rebuild the array with the correct, stable order.
        return array_values(array_merge([$configProvider], $frameworkProviders, $otherProviders));
    }

    /**
     * Get the providers from the main app config file.
     *
     * @return array
     */
    private function getProvidersFromAppConfig(): array
    {
        $configPath = $this->app->basePath('config/app.php');
        if (!file_exists($configPath)) {
            return [];
        }
        $appConfig = require $configPath;
        return $appConfig['providers'] ?? [];
    }
}
