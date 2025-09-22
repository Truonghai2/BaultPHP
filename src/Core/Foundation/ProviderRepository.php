<?php

namespace Core\Foundation;

use Core\Application;

/**
 * Manages the discovery, caching, and registration of service providers.
 *
 * This class centralizes the logic for finding all service providers from the core configuration
 * and from all enabled modules. It ensures that this discovery logic is not duplicated
 * between the application bootstrap process and the caching commands.
 */
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
        // Must register config provider first to be able to read other configs.
        $this->app->register(\App\Providers\ConfigServiceProvider::class);

        $coreProviders = $this->app->make('config')->get('app.providers', []);

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

        $allProviders = array_values(array_unique(array_merge($coreProviders, $moduleProviders)));
        sort($allProviders);

        return $allProviders;
    }
}
